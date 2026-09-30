<?php

namespace App\Models;

use Database\Factories\OrcamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Orçamento (RF17–RF21).
 *
 * @property int $id
 * @property int $ano
 * @property int $sequencia
 * @property int $revisao
 * @property string $situacao
 * @property string $valor_total
 * @property Carbon|null $data_envio
 * @property Carbon|null $valido_ate
 * @property Carbon|null $data_resposta
 */
class Orcamento extends Model
{
    /** @use HasFactory<OrcamentoFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const RASCUNHO = 'RASCUNHO';

    public const ENVIADO = 'ENVIADO';

    public const APROVADO = 'APROVADO';

    public const RECUSADO = 'RECUSADO';

    public const EXPIRADO = 'EXPIRADO';

    public const SUBSTITUIDO = 'SUBSTITUIDO';

    public const SITUACOES = [
        self::RASCUNHO => 'Rascunho',
        self::ENVIADO => 'Enviado',
        self::APROVADO => 'Aprovado',
        self::RECUSADO => 'Recusado',
        self::EXPIRADO => 'Expirado',
        self::SUBSTITUIDO => 'Substituído',
    ];

    public const MOTIVOS_RECUSA = ['PRECO' => 'Preço', 'PRAZO' => 'Prazo', 'DESISTENCIA' => 'Desistência', 'OUTRO' => 'Outro'];

    protected $fillable = ['cliente_id', 'contato_id', 'tipo_amostra_id', 'taxa_coleta', 'desconto_percentual', 'validade_dias',
        'condicoes_pagamento', 'observacoes'];

    protected $attributes = ['situacao' => self::RASCUNHO, 'revisao' => 1];

    protected function casts(): array
    {
        return [
            'subtotal_itens' => 'decimal:2', 'taxa_coleta' => 'decimal:2', 'desconto_percentual' => 'decimal:2',
            'valor_desconto' => 'decimal:2', 'valor_total' => 'decimal:2',
            'data_envio' => 'datetime', 'valido_ate' => 'date', 'data_resposta' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }

    public function tipoAmostra(): BelongsTo
    {
        return $this->belongsTo(TipoAmostra::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class)->orderBy('ordem');
    }

    public function substitui(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substitui_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'criado_por_id');
    }

    /** Ex.: "ORC-2026-0042". */
    public function numero(): string
    {
        return sprintf('ORC-%d-%04d', $this->ano, $this->sequencia);
    }

    /** Ex.: "ORC-2026-0042 r2". */
    public function numeroComRevisao(): string
    {
        return $this->numero().' r'.$this->revisao;
    }

    public function nomeSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }

    /** RN16: só o rascunho pode ser alterado. */
    public function editavel(): bool
    {
        return $this->situacao === self::RASCUNHO;
    }

    /** RF20 / RN16: nova revisão parte de um orçamento já enviado, expirado ou recusado. */
    public function permiteNovaRevisao(): bool
    {
        return in_array($this->situacao, [self::ENVIADO, self::EXPIRADO, self::RECUSADO], true);
    }

    public function nomeMotivoRecusa(): ?string
    {
        return $this->motivo_recusa ? (self::MOTIVOS_RECUSA[$this->motivo_recusa] ?? $this->motivo_recusa) : null;
    }
}
