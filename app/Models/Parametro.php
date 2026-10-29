<?php

namespace App\Models;

use Database\Factories\ParametroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Parâmetro de análise (RF13), ex.: pH, turbidez, coliformes totais.
 *
 * @property int $id
 * @property string $nome
 * @property string $unidade
 * @property string $categoria
 * @property string $preco
 * @property bool $ativo
 * @property bool $medido_em_campo RN46: informado pelo coletor no registro da coleta
 */
class Parametro extends Model
{
    /** @use HasFactory<ParametroFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const CATEGORIAS = ['FISICO_QUIMICA' => 'Físico-química', 'MICROBIOLOGICA' => 'Microbiológica'];

    protected $fillable = ['nome', 'unidade', 'metodo', 'limite_quantificacao', 'categoria', 'preco', 'ativo', 'medido_em_campo'];

    protected $attributes = ['ativo' => true, 'medido_em_campo' => false];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'preco' => 'decimal:2', 'medido_em_campo' => 'boolean'];
    }

    public function pacotes(): BelongsToMany
    {
        return $this->belongsToMany(Pacote::class, 'pacote_parametro');
    }

    public function nomeCategoria(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }
}
