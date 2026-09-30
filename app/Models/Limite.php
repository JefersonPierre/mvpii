<?php

namespace App\Models;

use App\Support\Numero;
use Database\Factories\LimiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Limite de um parâmetro numa legislação para um tipo de amostra (RF16, RN06).
 *
 * @property int $id
 * @property string $tipo
 * @property string|null $valor_minimo
 * @property string|null $valor_maximo
 */
class Limite extends Model
{
    /** @use HasFactory<LimiteFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const TIPOS = ['MAXIMO' => 'Máximo', 'MINIMO' => 'Mínimo', 'FAIXA' => 'Faixa', 'AUSENCIA' => 'Ausência'];

    protected $fillable = ['parametro_id', 'tipo_amostra_id', 'tipo', 'valor_minimo', 'valor_maximo', 'observacao'];

    public function legislacao(): BelongsTo
    {
        return $this->belongsTo(Legislacao::class);
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(Parametro::class);
    }

    public function tipoAmostra(): BelongsTo
    {
        return $this->belongsTo(TipoAmostra::class);
    }

    public function nomeTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** Ex.: "6 a 9,5", "≤ 5 uT", "≥ 0,2 mg/L" ou "Ausente". */
    public function descricao(): string
    {
        $unidade = $this->parametro?->unidade;
        $sufixo = $unidade ? " {$unidade}" : '';

        return match ($this->tipo) {
            'FAIXA' => Numero::decimal($this->valor_minimo).' a '.Numero::decimal($this->valor_maximo).$sufixo,
            'MAXIMO' => '≤ '.Numero::decimal($this->valor_maximo).$sufixo,
            'MINIMO' => '≥ '.Numero::decimal($this->valor_minimo).$sufixo,
            'AUSENCIA' => 'Ausente',
            default => '',
        };
    }
}
