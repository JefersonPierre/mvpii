<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Legislacao;
use App\Models\Limite;
use App\Models\Parametro;
use App\Models\Resultado;
use App\Models\Usuario;
use App\Support\Conformidade;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// MOD12 – UC24 Lançar resultados (RF48–RF50, RN46, RN50–RN52) e UC25 Revisar e liberar (RF51, RN53), com histórico (RN08).
class ResultadosAmostras
{
    public const ENTIDADE = 'RESULTADO';

    public const CAMPOS_AUDITADOS = ['parametro', 'resultado', 'data_analise', 'observacoes', 'origem', 'limite', 'conformidade'];

    public function __construct(
        private readonly Auditoria $auditoria,
        private readonly AgendamentoColetas $agendamento,
    ) {}

    /**
     * RN51: legislações vigentes na data da coleta para o tipo de amostra (RN07).
     *
     * @return EloquentCollection<int, Legislacao>
     */
    public function legislacoesAplicaveis(Amostra $amostra): EloquentCollection
    {
        $amostra->loadMissing('coleta.orcamento');
        $data = ($amostra->coleta->data_hora_coleta ?? $amostra->coleta->data)->toDateString();

        return Legislacao::query()
            ->whereHas('tiposAmostra', fn ($q) => $q->whereKey($amostra->coleta->orcamento->tipo_amostra_id))
            ->whereDate('inicio_vigencia', '<=', $data)
            ->where(fn ($q) => $q->whereNull('fim_vigencia')->orWhereDate('fim_vigencia', '>=', $data))
            ->orderByNome()->get();
    }

    /** Limite da legislação para o parâmetro e o tipo de amostra; null quando a legislação não tem limite (Sem limite). */
    public function limite(?Legislacao $legislacao, Amostra $amostra, Parametro $parametro): ?Limite
    {
        if (! $legislacao) {
            return null;
        }

        return $legislacao->limites()->where(['parametro_id' => $parametro->id,
            'tipo_amostra_id' => $amostra->coleta->orcamento->tipo_amostra_id])->first();
    }

    /**
     * Linhas da tela de resultados (P17): parâmetro, limite e resultado já lançado.
     *
     * @return Collection<int, array{parametro: Parametro, limite: Limite|null, resultado: Resultado|null}>
     */
    public function linhas(Amostra $amostra, ?Legislacao $legislacao): Collection
    {
        $resultados = $amostra->resultados()->with('lancadoPor')->get()->keyBy('parametro_id');

        return $amostra->parametrosAnalise()->map(fn (Parametro $p) => [
            'parametro' => $p,
            'limite' => $this->limite($legislacao, $amostra, $p),
            'resultado' => $resultados->get($p->id),
        ]);
    }

    /**
     * RN46: medições de campo informadas pelo coletor viram resultados da amostra, com origem Campo. A legislação só é
     * definida no primeiro lançamento no laboratório; até lá, a medição fica sem comparação.
     *
     * @param  array<int|string, string|null>  $medicoes  parametro_id => valor, já no formato do banco
     */
    public function registrarMedicoesCampo(Amostra $amostra, array $medicoes, int $usuarioId, Carbon $dataColeta): void
    {
        $deCampo = $amostra->parametrosDeCampo()->keyBy('id');
        foreach ($medicoes as $parametroId => $valor) {
            $parametro = $deCampo->get((int) $parametroId);
            if (! $parametro || $valor === null || $valor === '') {
                continue;
            }
            $resultado = Resultado::firstOrNew(['amostra_id' => $amostra->id, 'parametro_id' => $parametro->id]);
            $antes = $resultado->exists ? $this->valores($resultado) : [];
            $resultado->forceFill([
                'valor' => $valor, 'menor_que_lq' => false, 'presenca' => null, 'origem' => Resultado::CAMPO,
                'data_analise' => $dataColeta->toDateString(), 'lancado_por_id' => $usuarioId, 'lancado_em' => now(),
            ]);
            $resultado->setRelation('parametro', $parametro);
            $resultado->calcularConformidade();
            $resultado->save();
            $this->auditar($resultado, $antes);
        }
    }

    /**
     * UC24: grava os resultados informados e recalcula a conformidade. Com $enviarParaRevisao, exige todos os
     * parâmetros e passa a amostra para Em revisão (UC24 4a).
     *
     * @param  array{legislacao_id?: int|string|null,
     *               resultados: array<int|string, array{valor?: string|null, menor_que_lq?: bool, presenca?: string|null,
     *               data_analise?: string|null, observacoes?: string|null}>}  $dados
     */
    public function salvar(Amostra $amostra, array $dados, bool $enviarParaRevisao): void
    {
        DB::transaction(function () use ($amostra, $dados, $enviarParaRevisao) {
            $amostra = Amostra::with(['coleta.orcamento', 'itens'])->lockForUpdate()->findOrFail($amostra->id);
            if (! in_array($amostra->situacao, Amostra::RECEBEM_RESULTADOS, true)) {
                throw ValidationException::withMessages(['amostra' => 'Só amostras Recebidas ou Em análise recebem resultados.']);
            }

            $legislacao = $this->definirLegislacao($amostra, $dados['legislacao_id'] ?? null);
            $informados = $dados['resultados'] ?? [];
            $pendentes = [];

            foreach ($this->linhas($amostra, $legislacao) as ['parametro' => $parametro, 'limite' => $limite, 'resultado' => $resultado]) {
                $linha = $informados[$parametro->id] ?? null;
                $resultado ??= new Resultado(['amostra_id' => $amostra->id, 'parametro_id' => $parametro->id]);
                $resultado->forceFill(['amostra_id' => $amostra->id, 'parametro_id' => $parametro->id]);
                $resultado->setRelation('parametro', $parametro);
                $antes = $resultado->exists ? $this->valores($resultado) : [];

                if ($linha !== null) {
                    $this->aplicarLinha($resultado, $linha, $limite);
                }
                $this->copiarLimite($resultado, $limite);
                $resultado->calcularConformidade();

                if ($resultado->temResultado() || $resultado->exists) {
                    $resultado->save();
                    $this->auditar($resultado, $antes);
                }
                if (! $resultado->temResultado()) {
                    $pendentes[] = $parametro->nome;
                }
            }

            if ($enviarParaRevisao && $pendentes) {
                throw ValidationException::withMessages(['resultados' => 'Informe o resultado de todos os parâmetros antes de enviar para revisão. Faltam: '.implode(', ', $pendentes).'.']);
            }

            $amostra->unsetRelation('resultados');
            $this->agendamento->mudarAmostra($amostra, $enviarParaRevisao ? Amostra::EM_REVISAO : Amostra::EM_ANALISE, [
                'legislacao_id' => $legislacao?->id,
                'conformidade' => $this->conclusao($amostra, $legislacao),
                'enviada_revisao_em' => $enviarParaRevisao ? now() : $amostra->enviada_revisao_em,
                'motivo_devolucao' => $enviarParaRevisao ? null : $amostra->motivo_devolucao,
            ]);
        });
    }

    /** RN53: libera o Analista ou Administrador que não lançou nenhum resultado da amostra. */
    public function podeLiberar(Amostra $amostra, Usuario $usuario): bool
    {
        return $usuario->can('resultados.liberar')
            && ! $amostra->resultados()->where('lancado_por_id', $usuario->id)->exists();
    }

    /** UC25: resultados liberados ficam bloqueados para alteração (RN53) e a amostra fica pronta para o laudo. */
    public function liberar(Amostra $amostra): void
    {
        DB::transaction(function () use ($amostra) {
            $amostra = $this->emRevisao($amostra);
            $this->agendamento->mudarAmostra($amostra, Amostra::LIBERADA, [
                'liberada_em' => now(), 'liberada_por_id' => Auth::id(), 'motivo_devolucao' => null,
            ]);
        });
    }

    /** UC25 3a: devolve para correção, com motivo; a amostra volta para Em análise. */
    public function devolver(Amostra $amostra, string $motivo): void
    {
        DB::transaction(function () use ($amostra, $motivo) {
            $amostra = $this->emRevisao($amostra);
            $this->agendamento->mudarAmostra($amostra, Amostra::EM_ANALISE, ['motivo_devolucao' => $motivo]);
        });
    }

    /** RN52: conclusão da amostra pelos resultados gravados. */
    public function conclusao(Amostra $amostra, ?Legislacao $legislacao): string
    {
        return Conformidade::conclusao($amostra->resultados()->pluck('conformidade'), $legislacao !== null);
    }

    /** Valores do histórico (RN08). */
    public function valores(Resultado $resultado): array
    {
        return [
            'parametro' => $resultado->parametro?->nome,
            'resultado' => $resultado->valorFormatado(),
            'data_analise' => $resultado->data_analise?->format('d/m/Y'),
            'observacoes' => $resultado->observacoes,
            'origem' => Resultado::ORIGENS[$resultado->origem] ?? $resultado->origem,
            'limite' => $resultado->limite_tipo ? $resultado->limiteFormatado() : null,
            'conformidade' => $resultado->nomeConformidade(),
        ];
    }

    /** RN51: legislação já definida; senão a única vigente ou a escolhida pelo Analista entre as vigentes. */
    private function definirLegislacao(Amostra $amostra, int|string|null $escolhida): ?Legislacao
    {
        $aplicaveis = $this->legislacoesAplicaveis($amostra);
        if ($amostra->legislacao_id) {
            return $aplicaveis->firstWhere('id', $amostra->legislacao_id) ?? Legislacao::find($amostra->legislacao_id);
        }
        if ($aplicaveis->count() <= 1) {
            return $aplicaveis->first();
        }
        $legislacao = $aplicaveis->firstWhere('id', (int) $escolhida);
        if (! $legislacao) {
            throw ValidationException::withMessages(['legislacao_id' => 'Há mais de uma legislação vigente para este tipo de amostra: escolha a legislação de referência.']);
        }

        return $legislacao;
    }

    /** RN50: valor, "< LQ" ou Presente/Ausente; quem mudou o valor passa a ser quem o lançou (RN53). */
    private function aplicarLinha(Resultado $resultado, array $linha, ?Limite $limite): void
    {
        $ausencia = $limite?->tipo === 'AUSENCIA';
        $novo = [
            'valor' => $ausencia || ! empty($linha['menor_que_lq']) ? null : ($linha['valor'] ?? null),
            'menor_que_lq' => ! $ausencia && ! empty($linha['menor_que_lq']),
            'presenca' => $ausencia ? ($linha['presenca'] ?? null) : null,
        ];
        $numero = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $mudou = ! $resultado->exists
            || $numero($resultado->valor) !== $numero($novo['valor'])
            || $resultado->menor_que_lq !== $novo['menor_que_lq']
            || $resultado->presenca !== $novo['presenca'];

        $resultado->forceFill([
            'data_analise' => $linha['data_analise'] ?? $resultado->data_analise,
            'observacoes' => $linha['observacoes'] ?? null,
        ]);
        if ($mudou) {
            $resultado->forceFill([...$novo, 'origem' => Resultado::LABORATORIO, 'lancado_por_id' => Auth::id(), 'lancado_em' => now()]);
        }
    }

    /** O limite é copiado no resultado, para o laudo não mudar se o catálogo mudar depois. */
    private function copiarLimite(Resultado $resultado, ?Limite $limite): void
    {
        $resultado->forceFill([
            'limite_id' => $limite?->id,
            'limite_tipo' => $limite?->tipo,
            'limite_minimo' => $limite?->valor_minimo,
            'limite_maximo' => $limite?->valor_maximo,
        ]);
    }

    private function emRevisao(Amostra $amostra): Amostra
    {
        $amostra = Amostra::lockForUpdate()->findOrFail($amostra->id);
        if ($amostra->situacao !== Amostra::EM_REVISAO) {
            throw ValidationException::withMessages(['amostra' => 'Só amostras Em revisão podem ser liberadas ou devolvidas.']);
        }
        if (! $this->podeLiberar($amostra, Auth::user())) {
            throw ValidationException::withMessages(['amostra' => 'Você lançou resultados desta amostra: a revisão deve ser feita por outro usuário (RN53).']);
        }

        return $amostra;
    }

    private function auditar(Resultado $resultado, array $antes): void
    {
        $this->auditoria->registrar(self::ENTIDADE, $resultado->id, $antes === [] ? Auditoria::INCLUSAO : Auditoria::ALTERACAO,
            $antes, $this->valores($resultado), self::CAMPOS_AUDITADOS);
    }
}
