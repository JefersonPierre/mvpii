<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Coleta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// UC16 – Registrar coleta (RF33, RN28, RN31, RN32, RN33), com histórico (RNF12).
class RegistroColetas
{
    public function __construct(
        private readonly Rastreabilidade $rastreio,
        private readonly AgendamentoColetas $agendamento,
    ) {}

    /**
     * Coleta Realizada: as amostras marcadas ficam Coletadas e as não coletadas voltam ao saldo do orçamento (RN28).
     *
     * @param  array{data_hora_coleta: string, condicoes_tempo?: string|null,
     *               amostras: array<int, array{situacao: string, temperatura?: string|null, observacoes?: string|null, motivo?: string|null}>}  $dados
     */
    public function registrar(Coleta $coleta, array $dados): void
    {
        DB::transaction(function () use ($coleta, $dados) {
            $previstas = $this->previstas($coleta);
            $linhas = $dados['amostras'];
            if (! collect($linhas)->contains(fn ($l) => $l['situacao'] === Amostra::COLETADA)) {
                throw ValidationException::withMessages(['amostras' => 'Marque ao menos uma amostra coletada ou registre a coleta como Não realizada.']);
            }

            $this->gravarColeta($coleta, Coleta::REALIZADA, $dados);
            foreach ($previstas as $amostra) {
                $linha = $linhas[$amostra->id];
                $coletada = $linha['situacao'] === Amostra::COLETADA;
                $this->agendamento->mudarAmostra($amostra, $coletada ? Amostra::COLETADA : Amostra::NAO_COLETADA, [
                    'temperatura_coleta' => $coletada ? ($linha['temperatura'] ?? null) : null,
                    'observacoes_coleta' => $linha['observacoes'] ?? null,
                    'motivo_nao_coleta' => $coletada ? null : $linha['motivo'],
                    'coletada_por_id' => Auth::id(),
                ]);
            }
        });
    }

    /**
     * UC16 3b: nenhuma amostra coletada. A coleta fica Não realizada, as amostras Não coletadas (saldo devolvido) e a
     * Recepção reagenda.
     *
     * @param  array{data_hora_coleta: string, condicoes_tempo?: string|null, motivo_nao_realizada: string, motivo_nao_realizada_detalhe?: string|null}  $dados
     */
    public function registrarNaoRealizada(Coleta $coleta, array $dados): void
    {
        DB::transaction(function () use ($coleta, $dados) {
            $previstas = $this->previstas($coleta);
            $this->gravarColeta($coleta, Coleta::NAO_REALIZADA, $dados);
            foreach ($previstas as $amostra) {
                $this->agendamento->mudarAmostra($amostra, Amostra::NAO_COLETADA, [
                    'motivo_nao_coleta' => $dados['motivo_nao_realizada'],
                    'observacoes_coleta' => $dados['motivo_nao_realizada_detalhe'] ?? null,
                    'coletada_por_id' => Auth::id(),
                ]);
            }
        });
    }

    /** @return Collection<int, Amostra> */
    private function previstas(Coleta $coleta)
    {
        Coleta::whereKey($coleta->id)->lockForUpdate()->first();
        $coleta->refresh();
        if (! $coleta->podeRegistrar()) {
            throw ValidationException::withMessages(['coleta' => 'Só a coleta Agendada pode ser registrada.']);
        }

        return $coleta->amostras()->where('situacao', Amostra::PREVISTA)->get();
    }

    private function gravarColeta(Coleta $coleta, string $situacao, array $dados): void
    {
        $antes = $this->rastreio->valoresColeta($coleta);
        $coleta->forceFill([
            'situacao' => $situacao,
            'data_hora_coleta' => $dados['data_hora_coleta'],
            'condicoes_tempo' => $dados['condicoes_tempo'] ?? null,
            'motivo_nao_realizada' => $dados['motivo_nao_realizada'] ?? null,
            'motivo_nao_realizada_detalhe' => $dados['motivo_nao_realizada_detalhe'] ?? null,
            'registrada_por_id' => Auth::id(),
        ])->save();
        $this->rastreio->registrarColeta($coleta, Auditoria::ALTERACAO, $antes);
    }
}
