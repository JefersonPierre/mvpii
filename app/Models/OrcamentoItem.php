<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item do orçamento: pacote ou parâmetro avulso, com nome e preço copiados do catálogo (RN15).
 *
 * @property string $tipo
 * @property string $descricao
 * @property string $preco_unitario
 * @property int $quantidade
 * @property string $subtotal
 */
class OrcamentoItem extends Model
{
    public $timestamps = false;

    protected $table = 'orcamento_itens';

    protected $fillable = ['tipo', 'pacote_id', 'parametro_id', 'descricao', 'parametros_incluidos', 'preco_unitario',
        'quantidade', 'subtotal', 'ordem'];

    protected function casts(): array
    {
        return ['preco_unitario' => 'decimal:2', 'subtotal' => 'decimal:2', 'quantidade' => 'integer'];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function pacote(): BelongsTo
    {
        return $this->belongsTo(Pacote::class);
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(Parametro::class);
    }

    /** Referência ao catálogo usada no formulário: "PACOTE:3" ou "PARAMETRO:7". */
    public function referencia(): string
    {
        return $this->tipo.':'.($this->tipo === 'PACOTE' ? $this->pacote_id : $this->parametro_id);
    }
}
