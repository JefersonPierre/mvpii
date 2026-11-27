<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Amostra de uma coleta, identificada por código (RN30), do agendamento ao recebimento no laboratório (RN33), aos
 * resultados (MOD12) e ao laudo (MOD13).
 *
 * @property int $id
 * @property int $coleta_id
 * @property int $ponto_coleta_id
 * @property string $codigo
 * @property string $situacao
 * @property string|null $temperatura_coleta
 * @property string|null $temperatura_recebimento
 * @property Carbon|null $recebida_em
 * @property bool $fora_criterios
 * @property int|null $legislacao_id
 * @property string|null $conformidade
 */
class Amostra extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const PREVISTA = 'PREVISTA';

    public const COLETADA = 'COLETADA';

    public const NAO_COLETADA = 'NAO_COLETADA';

    public const CANCELADA = 'CANCELADA';

    public const RECEBIDA = 'RECEBIDA';

    public const REJEITADA = 'REJEITADA';

    public const EM_ANALISE = 'EM_ANALISE';

    public const EM_REVISAO = 'EM_REVISAO';

    public const LIBERADA = 'LIBERADA';

    public const LAUDO_EMITIDO = 'LAUDO_EMITIDO';

    /** RN33 */
    public const SITUACOES = [
        self::PREVISTA => 'Prevista',
        self::COLETADA => 'Coletada',
        self::NAO_COLETADA => 'Não coletada',
        self::CANCELADA => 'Cancelada',
        self::RECEBIDA => 'Recebida',
        self::REJEITADA => 'Rejeitada',
        self::EM_ANALISE => 'Em análise',
        self::EM_REVISAO => 'Em revisão',
        self::LIBERADA => 'Liberada',
        self::LAUDO_EMITIDO => 'Laudo emitido',
    ];

    /** Situações depois do recebimento no laboratório (MOD12 e MOD13): a amostra segue no laboratório. */
    public const NO_LABORATORIO = [self::RECEBIDA, self::EM_ANALISE, self::EM_REVISAO, self::LIBERADA, self::LAUDO_EMITIDO];

    /** RN28: situações que ocupam o saldo do orçamento; as demais devolvem a amostra ao saldo. */
    public const OCUPAM_SALDO = [self::PREVISTA, self::COLETADA, ...self::NO_LABORATORIO];

    /** RN50: situações em que os resultados podem ser lançados ou corrigidos. */
    public const RECEBEM_RESULTADOS = [self::RECEBIDA, self::EM_ANALISE];

    public const CONFORME = 'CONFORME';

    public const NAO_CONFORME = 'NAO_CONFORME';

    public const INCONCLUSIVA = 'INCONCLUSIVA';

    public const SEM_LEGISLACAO = 'SEM_LEGISLACAO';

    /** RN52: conclusão da amostra. */
    public const CONFORMIDADES = [
        self::CONFORME => 'Conforme',
        self::NAO_CONFORME => 'Não conforme',
        self::INCONCLUSIVA => 'Inconclusiva',
        self::SEM_LEGISLACAO => 'Sem legislação de referência',
    ];

    /** RN35 */
    public const MOTIVOS_REJEICAO = [
        'FRASCO_DANIFICADO' => 'Frasco danificado',
        'VOLUME_INSUFICIENTE' => 'Volume insuficiente',
        'TEMPERATURA' => 'Temperatura fora do limite',
        'PRAZO_EXCEDIDO' => 'Prazo excedido',
        'IDENTIFICACAO_ILEGIVEL' => 'Identificação ilegível',
        'OUTRO' => 'Outro',
    ];

    /** Condições do frasco conferidas no recebimento (UC17). */
    public const CONDICOES_FRASCO = [
        'frasco_integro' => 'Frasco íntegro',
        'identificacao_legivel' => 'Identificação legível',
        'volume_suficiente' => 'Volume suficiente',
        'preservacao_adequada' => 'Preservação adequada',
    ];

    protected $attributes = ['situacao' => self::PREVISTA, 'fora_criterios' => false];

    protected function casts(): array
    {
        return [
            'temperatura_coleta' => 'decimal:1', 'temperatura_recebimento' => 'decimal:1', 'recebida_em' => 'datetime',
            'frasco_integro' => 'boolean', 'identificacao_legivel' => 'boolean', 'volume_suficiente' => 'boolean',
            'preservacao_adequada' => 'boolean', 'fora_criterios' => 'boolean',
            'enviada_revisao_em' => 'datetime', 'liberada_em' => 'datetime',
        ];
    }

    public function coleta(): BelongsTo
    {
        return $this->belongsTo(Coleta::class);
    }

    public function pontoColeta(): BelongsTo
    {
        return $this->belongsTo(PontoColeta::class);
    }

    /** Itens do orçamento analisados nesta amostra. */
    public function itens(): BelongsToMany
    {
        return $this->belongsToMany(OrcamentoItem::class, 'amostra_itens')->orderBy('ordem');
    }

    public function coletadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'coletada_por_id');
    }

    public function recebidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recebida_por_id');
    }

    public function liberadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'liberada_por_id');
    }

    /** RN51: legislação de referência, vigente na data da coleta para o tipo de amostra. */
    public function legislacao(): BelongsTo
    {
        return $this->belongsTo(Legislacao::class);
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(Resultado::class);
    }

    /** Laudos da amostra, da revisão mais recente para a mais antiga. */
    public function laudos(): HasMany
    {
        return $this->hasMany(Laudo::class)->orderByDesc('revisao');
    }

    public function laudoVigente(): HasOne
    {
        return $this->hasOne(Laudo::class)->where('situacao', Laudo::VIGENTE);
    }

    /** RN45: fotos do registro aplicado – as de um envio em conflito só valem depois de aceito. */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoAmostra::class)
            ->where(fn (Builder $q) => $q->whereNull('sincronizacao_id')
                ->orWhereHas('sincronizacao', fn (Builder $s) => $s->whereIn('situacao', [Sincronizacao::APLICADA, Sincronizacao::ACEITA])))
            ->orderBy('id');
    }

    /**
     * RN50: parâmetros a analisar – os dos itens do orçamento associados à amostra, com os parâmetros de cada pacote,
     * sem repetição e na ordem dos itens.
     *
     * @return EloquentCollection<int, Parametro>
     */
    public function parametrosAnalise(): EloquentCollection
    {
        $this->loadMissing(['itens.parametro', 'itens.pacote.parametros']);
        $parametros = new EloquentCollection;
        foreach ($this->itens as $item) {
            $lista = $item->tipo === 'PACOTE' ? ($item->pacote?->parametros ?? collect())->ordenarPorNome() : collect([$item->parametro]);
            foreach ($lista->filter() as $parametro) {
                if (! $parametros->contains('id', $parametro->id)) {
                    $parametros->push($parametro);
                }
            }
        }

        return $parametros;
    }

    /** RN46: parâmetros da amostra informados pelo coletor no registro da coleta. */
    public function parametrosDeCampo(): EloquentCollection
    {
        return $this->parametrosAnalise()->where('medido_em_campo', true)->values();
    }

    public function nomeConformidade(): ?string
    {
        return $this->conformidade ? (self::CONFORMIDADES[$this->conformidade] ?? $this->conformidade) : null;
    }

    /**
     * RN36: quem só tem o perfil Coletor vê apenas as amostras das próprias coletas.
     *
     * @param  Builder<Amostra>  $query
     */
    public function scopeVisiveisPara(Builder $query, Usuario $usuario): void
    {
        if (! $usuario->veTodasAsColetas()) {
            $query->whereHas('coleta', fn ($q) => $q->where('coletor_id', $usuario->id));
        }
    }

    public function nomeSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }

    public function nomeMotivoNaoColeta(): ?string
    {
        return $this->motivo_nao_coleta ? (Coleta::MOTIVOS_NAO_COLETA[$this->motivo_nao_coleta] ?? $this->motivo_nao_coleta) : null;
    }

    public function nomeMotivoRejeicao(): ?string
    {
        return $this->motivo_rejeicao ? (self::MOTIVOS_REJEICAO[$this->motivo_rejeicao] ?? $this->motivo_rejeicao) : null;
    }

    /** Nomes dos itens a analisar, ex.: "Potabilidade básica · Cloro residual livre". */
    public function descricaoItens(): string
    {
        return $this->itens->map(fn (OrcamentoItem $i) => preg_replace('/^Pacote /', '', $i->descricao))->implode(' · ');
    }
}
