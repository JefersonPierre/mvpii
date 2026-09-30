<?php

namespace App\Models;

use Database\Factories\PacoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pacote de análise (RF14): conjunto de parâmetros de um tipo de amostra, com preço próprio.
 *
 * @property int $id
 * @property string $nome
 * @property string $preco
 * @property bool $ativo
 */
class Pacote extends Model
{
    /** @use HasFactory<PacoteFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['nome', 'tipo_amostra_id', 'preco', 'ativo'];

    protected $attributes = ['ativo' => true];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'preco' => 'decimal:2'];
    }

    public function tipoAmostra(): BelongsTo
    {
        return $this->belongsTo(TipoAmostra::class);
    }

    public function parametros(): BelongsToMany
    {
        return $this->belongsToMany(Parametro::class, 'pacote_parametro')->orderByNome();
    }
}
