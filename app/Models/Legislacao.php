<?php

namespace App\Models;

use Database\Factories\LegislacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Legislação de referência (RF15), ex.: Portaria GM/MS nº 888/2021.
 *
 * @property int $id
 * @property string $nome
 * @property Carbon $inicio_vigencia
 * @property Carbon|null $fim_vigencia
 */
class Legislacao extends Model
{
    /** @use HasFactory<LegislacaoFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'legislacoes';

    protected $fillable = ['nome', 'orgao_emissor', 'inicio_vigencia', 'fim_vigencia', 'substituida_por_id'];

    protected function casts(): array
    {
        return ['inicio_vigencia' => 'date', 'fim_vigencia' => 'date'];
    }

    public function tiposAmostra(): BelongsToMany
    {
        return $this->belongsToMany(TipoAmostra::class, 'legislacao_tipo_amostra')->orderByNome();
    }

    public function limites(): HasMany
    {
        return $this->hasMany(Limite::class);
    }

    public function substituidaPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substituida_por_id');
    }

    /** RN07: vigente se já começou e não terminou (fim de vigência vazio ou futuro). */
    public function vigente(): bool
    {
        return $this->inicio_vigencia->lte(today()) && ($this->fim_vigencia === null || $this->fim_vigencia->gte(today()));
    }

    /** RN07: após o fim da vigência, os limites ficam só para consulta histórica. */
    public function encerrada(): bool
    {
        return $this->fim_vigencia !== null && $this->fim_vigencia->lt(today());
    }

    public function situacao(): string
    {
        return match (true) {
            $this->encerrada() => 'Encerrada',
            $this->inicio_vigencia->gt(today()) => 'Futura',
            default => 'Vigente',
        };
    }
}
