<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Support\Roteiro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// UC16 – Registrar coleta (RF33, RN28, RN31, RN32, RN33), com histórico (RNF12). No Entregável 3, também pelo celular
// (UC21): medições de campo (RN46), localização (RN44) e origem do registro (RN41).
class RegistroColetas
{
    public function __construct(
        private readonly Rastreabilidade $rastreio,
        private readonly AgendamentoColetas $agendamento,
        private readonly ResultadosAmostras $resultados,
    ) {}

    /**
     * Coleta Realizada: as amostras marcadas ficam Coletadas e as não coletadas voltam ao saldo do orçamento (RN28).
     *
     * @param  array{data_hora_coleta: string, condicoes_tempo?: string|null, origem_registro?: string|null,
     *               latitude?: float|string|null, longitude?: float|string|null,
     *               amostras: array<int, array{situacao: string, temperatura?: string|null, observacoes?: string|null,
     *               motivo?: string|null, medicoes?: array<int, string|null>}>}  $dados
     * @param  int|null  $responsavelId  quem fez a coleta; padrão: o usuário conectado (no aceite de um conflito, o coletor)
     */
    public function registrar(Coleta $coleta, array $dados, ?int $responsavelId = null): void
    {
        $responsavelId ??= (int) Auth::id();
        DB::transaction(function () use ($coleta, $dados, $responsavelId) {
            $previstas = $this->previstas($coleta);
            $linhas = $dados['amostras'];
            if (! collect($linhas)->contains(fn ($l) => $l['situacao'] === Amostra::COLETADA)) {
                throw ValidationException::withMessages(['amostras' => 'Marque ao menos uma amostra coletada ou registre a coleta como Não realizada.']);
            }

            $this->gravarColeta($coleta, Coleta::REALIZADA, $dados, $responsavelId);
            $dataColeta = Carbon::parse($dados['data_hora_coleta']);
            foreach ($previstas as $amostra) {
                $linha = $linhas[$amostra->id] ?? throw ValidationException::withMessages(['amostras' => "Informe a situação da amostra {$amostra->codigo}."]);
                $coletada = $linha['situacao'] === Amostra::COLETADA;
                $this->agendamento->mudarAmostra($amostra, $coletada ? Amostra::COLETADA : Amostra::NAO_COLETADA, [
                    'temperatura_coleta' => $coletada ? ($linha['temperatura'] ?? null) : null,
                    'observacoes_coleta' => $linha['observacoes'] ?? null,
                    'motivo_nao_coleta' => $coletada ? null : $linha['motivo'],
                    'coletada_por_id' => $responsavelId,
                ]);
                if ($coletada) {
                    $this->resultados->registrarMedicoesCampo($amostra, $linha['medicoes'] ?? [], $responsavelId, $dataColeta);
                }
            }
        });
    }

    /**
     * UC16 3b: nenhuma amostra coletada. A coleta fica Não realizada, as amostras Não coletadas (saldo devolvido) e a
     * Recepção reagenda.
     *
     * @param  array{data_hora_coleta: string, condicoes_tempo?: string|null, motivo_nao_realizada: string, motivo_nao_realizada_detalhe?: string|null}  $dados
     */
    public function registrarNaoRealizada(Coleta $coleta, array $dados, ?int $responsavelId = null): void
    {
        $responsavelId ??= (int) Auth::id();
        DB::transaction(function () use ($coleta, $dados, $responsavelId) {
            $previstas = $this->previstas($coleta);
            $this->gravarColeta($coleta, Coleta::NAO_REALIZADA, $dados, $responsavelId);
            foreach ($previstas as $amostra) {
                $this->agendamento->mudarAmostra($amostra, Amostra::NAO_COLETADA, [
                    'motivo_nao_coleta' => $dados['motivo_nao_realizada'],
                    'observacoes_coleta' => $dados['motivo_nao_realizada_detalhe'] ?? null,
                    'coletada_por_id' => $responsavelId,
                ]);
            }
        });
    }

    /**
     * RN44: distância entre a localização da coleta e o ponto mais próximo da coleta com coordenadas.
     */
    public static function distanciaAoPonto(Coleta $coleta, float $lat, float $lng): ?int
    {
        $distancias = $coleta->amostras->map->pontoColeta->filter(fn ($p) => $p?->temCoordenadas())
            ->map(fn ($p) => Roteiro::distanciaMetros($lat, $lng, $p->latitude, $p->longitude));

        return $distancias->isEmpty() ? null : (int) round($distancias->min());
    }

    /** @return Collection<int, Amostra> */
    private function previstas(Coleta $coleta)
    {
        Coleta::whereKey($coleta->id)->lockForUpdate()->first();
        $coleta->refresh();
        if (! $coleta->podeRegistrar()) {
            throw ValidationException::withMessages(['coleta' => 'Só a coleta Agendada pode ser registrada.']);
        }

        return $coleta->amostras()->with(['itens.pacote.parametros', 'itens.parametro'])->where('situacao', Amostra::PREVISTA)->get();
    }

    private function gravarColeta(Coleta $coleta, string $situacao, array $dados, int $responsavelId): void
    {
        $antes = $this->rastreio->valoresColeta($coleta);
        $lat = isset($dados['latitude']) ? (float) $dados['latitude'] : null;
        $lng = isset($dados['longitude']) ? (float) $dados['longitude'] : null;
        $distancia = $lat !== null && $lng !== null ? self::distanciaAoPonto($coleta->loadMissing('amostras.pontoColeta'), $lat, $lng) : null;

        $coleta->forceFill([
            'situacao' => $situacao,
            'data_hora_coleta' => $dados['data_hora_coleta'],
            'condicoes_tempo' => $dados['condicoes_tempo'] ?? null,
            'motivo_nao_realizada' => $dados['motivo_nao_realizada'] ?? null,
            'motivo_nao_realizada_detalhe' => $dados['motivo_nao_realizada_detalhe'] ?? null,
            'registrada_por_id' => $responsavelId,
            'origem_registro' => $dados['origem_registro'] ?? 'COMPUTADOR',
            'registro_recebido_em' => $dados['registro_recebido_em'] ?? now(),
            'latitude_coleta' => $lat,
            'longitude_coleta' => $lng,
            'distancia_ponto_metros' => $distancia,
            'conferir_localizacao' => $distancia !== null && $distancia > Coleta::DISTANCIA_MAXIMA_METROS,
        ])->save();
        $this->rastreio->registrarColeta($coleta, Auditoria::ALTERACAO, $antes);
    }
}
