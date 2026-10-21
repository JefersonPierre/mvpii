<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\RegistroAuditoria;
use App\Support\Numero;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * RNF12 / RN08: cada mudança da coleta e da amostra fica no histórico (usuário, data e hora), formando a cadeia de
 * custódia. Daqui saem também a linha do tempo da amostra (RF36) e os valores auditados.
 */
class Rastreabilidade
{
    public const COLETA = 'COLETA';

    public const AMOSTRA = 'AMOSTRA';

    public const CAMPOS_COLETA = ['coletor', 'data', 'periodo', 'horario', 'instrucoes', 'situacao', 'motivo',
        'data_hora_coleta', 'condicoes_tempo', 'motivo_nao_realizada', 'motivo_nao_realizada_detalhe'];

    public const CAMPOS_AMOSTRA = ['codigo', 'ponto', 'itens', 'situacao', 'temperatura_coleta', 'observacoes_coleta',
        'motivo_nao_coleta', 'recebida_em', 'temperatura_recebimento', 'condicoes_frasco', 'fora_criterios',
        'observacoes_recebimento', 'motivo_rejeicao'];

    /** Nomes dos campos nas telas de histórico. */
    public const NOMES_CAMPOS = [
        'coletor' => 'Coletor', 'data' => 'Data', 'periodo' => 'Período', 'horario' => 'Horário', 'instrucoes' => 'Instruções',
        'situacao' => 'Situação', 'motivo' => 'Motivo', 'data_hora_coleta' => 'Data e hora da coleta',
        'condicoes_tempo' => 'Condições do tempo', 'motivo_nao_realizada' => 'Motivo (não realizada)',
        'motivo_nao_realizada_detalhe' => 'Detalhe do motivo', 'codigo' => 'Código', 'ponto' => 'Ponto de coleta',
        'itens' => 'Itens a analisar', 'temperatura_coleta' => 'Temperatura na coleta',
        'observacoes_coleta' => 'Observações da coleta', 'motivo_nao_coleta' => 'Motivo (não coletada)',
        'recebida_em' => 'Data e hora do recebimento', 'temperatura_recebimento' => 'Temperatura no recebimento',
        'condicoes_frasco' => 'Condições do frasco', 'fora_criterios' => 'Fora dos critérios',
        'observacoes_recebimento' => 'Observações do recebimento', 'motivo_rejeicao' => 'Motivo da rejeição',
    ];

    public function __construct(private readonly Auditoria $auditoria) {}

    public function registrarColeta(Coleta $coleta, string $acao, array $antes): void
    {
        $this->auditoria->registrar(self::COLETA, $coleta->id, $acao, $antes, $this->valoresColeta($coleta), self::CAMPOS_COLETA);
    }

    public function registrarAmostra(Amostra $amostra, string $acao, array $antes): void
    {
        $this->auditoria->registrar(self::AMOSTRA, $amostra->id, $acao, $antes, $this->valoresAmostra($amostra), self::CAMPOS_AMOSTRA);
    }

    /** @return array<string, mixed> */
    public function valoresColeta(Coleta $coleta): array
    {
        $coleta->loadMissing('coletor');

        return [
            'coletor' => $coleta->coletor?->nome,
            'data' => $coleta->data?->format('d/m/Y'),
            'periodo' => $coleta->nomePeriodo(),
            'horario' => $coleta->horarioFormatado(),
            'instrucoes' => $coleta->instrucoes,
            'situacao' => $coleta->nomeSituacao(),
            'motivo' => $coleta->motivo,
            'data_hora_coleta' => $coleta->data_hora_coleta?->format('d/m/Y H:i'),
            'condicoes_tempo' => $coleta->condicoes_tempo,
            'motivo_nao_realizada' => $coleta->nomeMotivoNaoRealizada(),
            'motivo_nao_realizada_detalhe' => $coleta->motivo_nao_realizada_detalhe,
        ];
    }

    /** @return array<string, mixed> */
    public function valoresAmostra(Amostra $amostra): array
    {
        $amostra->loadMissing(['pontoColeta', 'itens']);
        $condicoes = collect(Amostra::CONDICOES_FRASCO)
            ->filter(fn ($nome, $campo) => $amostra->{$campo} !== null)
            ->map(fn ($nome, $campo) => $nome.': '.($amostra->{$campo} ? 'sim' : 'não'))
            ->implode('; ');

        return [
            'codigo' => $amostra->codigo,
            'ponto' => $amostra->pontoColeta?->identificacao,
            'itens' => $amostra->descricaoItens(),
            'situacao' => $amostra->nomeSituacao(),
            'temperatura_coleta' => $amostra->temperatura_coleta,
            'observacoes_coleta' => $amostra->observacoes_coleta,
            'motivo_nao_coleta' => $amostra->nomeMotivoNaoColeta(),
            'recebida_em' => $amostra->recebida_em?->format('d/m/Y H:i'),
            'temperatura_recebimento' => $amostra->temperatura_recebimento,
            'condicoes_frasco' => $condicoes,
            'fora_criterios' => $amostra->recebida_em ? ($amostra->fora_criterios ? 'Sim' : 'Não') : null,
            'observacoes_recebimento' => $amostra->observacoes_recebimento,
            'motivo_rejeicao' => $amostra->nomeMotivoRejeicao(),
        ];
    }

    /**
     * RF36: linha do tempo da amostra, do agendamento ao recebimento. Junta as mudanças da amostra e as da sua coleta
     * (agendamento, reagendamentos, cancelamento, coleta não realizada).
     *
     * @return Collection<int, array{titulo: string, data_hora: Carbon, usuario: string, detalhes: list<string>}>
     */
    public function linhaDoTempo(Amostra $amostra): Collection
    {
        $registros = RegistroAuditoria::with('responsavel')
            ->where(fn ($q) => $q->where(['entidade' => self::AMOSTRA, 'registro_id' => $amostra->id])
                ->orWhere(fn ($q) => $q->where(['entidade' => self::COLETA, 'registro_id' => $amostra->coleta_id])))
            ->orderBy('data_hora')->orderBy('id')
            ->get();

        // Cada etapa é o conjunto de campos gravados juntos (mesma entidade, momento e usuário).
        return $registros
            ->groupBy(fn (RegistroAuditoria $r) => implode('|', [$r->entidade, $r->acao, $r->data_hora->format('Y-m-d H:i:s'), $r->usuario_id]))
            ->map(fn (Collection $etapa) => $this->etapa($etapa->keyBy('campo'), $etapa->first()))
            ->filter()
            ->values();
    }

    /** @param  Collection<string, RegistroAuditoria>  $campos */
    private function etapa(Collection $campos, RegistroAuditoria $primeiro): ?array
    {
        $novo = fn (string $campo) => $campos->get($campo)?->valor_novo;
        $detalhes = [];

        if ($primeiro->entidade === self::COLETA && $primeiro->acao === Auditoria::INCLUSAO) {
            $titulo = 'Agendada';
            $detalhes[] = 'Coleta em '.collect([$novo('data'), $novo('periodo'), $novo('horario')])->filter()->implode(' · ')
                .' com '.$novo('coletor');
        } elseif ($primeiro->entidade === self::COLETA) {
            if (($campos->hasAny(['data', 'periodo', 'horario', 'coletor']) || $novo('situacao') === 'Agendada')
                && $novo('situacao') !== 'Cancelada') {
                $titulo = 'Coleta reagendada';
                $detalhes[] = collect(['data', 'periodo', 'horario', 'coletor'])
                    ->filter(fn ($c) => $campos->has($c))
                    ->map(fn ($c) => self::NOMES_CAMPOS[$c].': '.($novo($c) ?? '—'))->implode(' · ');
            } elseif ($novo('situacao') === 'Cancelada') {
                $titulo = 'Coleta cancelada';
            } elseif ($novo('situacao') === 'Não realizada') {
                $titulo = 'Coleta não realizada';
                $detalhes[] = collect([$novo('motivo_nao_realizada'), $novo('motivo_nao_realizada_detalhe')])->filter()->implode(' – ');
            } else {
                return null; // a coleta realizada já aparece na etapa "Coletada" da amostra
            }
            if ($novo('motivo')) {
                $detalhes[] = 'Motivo: '.$novo('motivo');
            }
        } elseif ($primeiro->acao === Auditoria::INCLUSAO) {
            return null; // a etapa "Agendada" vem da inclusão da coleta
        } elseif ($campos->has('situacao')) {
            $titulo = $novo('situacao');
            $temperatura = $novo('temperatura_recebimento') ?? $novo('temperatura_coleta');
            $detalhes = array_filter([
                $temperatura !== null ? Numero::decimal($temperatura).' °C' : null,
                $novo('motivo_nao_coleta'),
                $novo('motivo_rejeicao'),
                $novo('observacoes_coleta'),
                $novo('observacoes_recebimento'),
                $novo('fora_criterios') === 'Sim' ? 'Recebida fora dos critérios de prazo ou temperatura' : null,
            ]);
        } else {
            return null;
        }

        return [
            'titulo' => $titulo,
            'data_hora' => $primeiro->data_hora,
            'usuario' => $primeiro->responsavel?->nome ?? 'Sistema',
            'detalhes' => array_values(array_filter($detalhes)),
        ];
    }
}
