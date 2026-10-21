<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Amostra de uma coleta, identificada por código (RN30), do agendamento ao recebimento no laboratório (RN33).
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

    /** RN33 */
    public const SITUACOES = [
        self::PREVISTA => 'Prevista',
        self::COLETADA => 'Coletada',
        self::NAO_COLETADA => 'Não coletada',
        self::CANCELADA => 'Cancelada',
        self::RECEBIDA => 'Recebida',
        self::REJEITADA => 'Rejeitada',
    ];

    /** RN28: situações que ocupam o saldo do orçamento; as demais devolvem a amostra ao saldo. */
    public const OCUPAM_SALDO = [self::PREVISTA, self::COLETADA, self::RECEBIDA];

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
