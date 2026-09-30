<?php

namespace App\Models;

use Database\Factories\TipoAmostraFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de amostra do catálogo técnico (RF12), ex.: água potável, efluente.
 *
 * @property int $id
 * @property string $nome
 * @property bool $ativo
 */
class TipoAmostra extends Model
{
    /** @use HasFactory<TipoAmostraFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'tipos_amostra';

    protected $fillable = ['nome', 'descricao', 'ativo'];

    protected $attributes = ['ativo' => true];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function pontosColeta(): HasMany
    {
        return $this->hasMany(PontoColeta::class);
    }

    /** @param  Builder<TipoAmostra>  $query */
    public function scopeAtivos(Builder $query): void
    {
        $query->where('ativo', true)->orderBy('nome');
    }
}
