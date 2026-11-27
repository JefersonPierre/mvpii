<?php

namespace App\Models;

use App\Support\Conformidade;
use App\Support\Numero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Resultado de um parâmetro numa amostra (RF48), com o limite copiado da legislação de referência e a conformidade
 * calculada pelo sistema (RN52).
 *
 * @property int $id
 * @property int $amostra_id
 * @property int $parametro_id
 * @property string|null $valor
 * @property bool $menor_que_lq
 * @property string|null $presenca
 * @property Carbon|null $data_analise
 * @property string $origem
 * @property string|null $limite_tipo
 * @property string|null $conformidade
 * @property int|null $lancado_por_id
 */
class Resultado extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const CAMPO = 'CAMPO';

    public const LABORATORIO = 'LABORATORIO';

    public const ORIGENS = [self::CAMPO => 'Campo', self::LABORATORIO => 'Laboratório'];

    public const PRESENCAS = ['AUSENTE' => 'Ausente', 'PRESENTE' => 'Presente'];

    public const CONFORMIDADES = [
        Conformidade::CONFORME => 'Conforme',
        Conformidade::NAO_CONFORME => 'Não conforme',
        Conformidade::INCONCLUSIVO => 'Inconclusivo',
        Conformidade::SEM_LIMITE => 'Sem limite',
    ];

    protected $fillable = ['amostra_id', 'parametro_id'];

    protected $attributes = ['menor_que_lq' => false, 'origem' => self::LABORATORIO];

    protected function casts(): array
    {
        return ['menor_que_lq' => 'boolean', 'data_analise' => 'date', 'lancado_em' => 'datetime'];
    }

    public function amostra(): BelongsTo
    {
        return $this->belongsTo(Amostra::class);
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(Parametro::class);
    }

    public function limite(): BelongsTo
    {
        return $this->belongsTo(Limite::class);
    }

    public function lancadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'lancado_por_id');
    }

    public function temResultado(): bool
    {
        return $this->menor_que_lq || $this->presenca !== null || $this->valor !== null;
    }

    /** Ex.: "7,2", "< LQ" ou "Ausente". */
    public function valorFormatado(): string
    {
        return match (true) {
            $this->menor_que_lq => '< LQ',
            $this->presenca !== null => self::PRESENCAS[$this->presenca] ?? $this->presenca,
            $this->valor !== null => Numero::decimal($this->valor),
            default => '',
        };
    }

    /** Limite copiado no lançamento, como no catálogo: "6 a 9", "≤ 5" ou "Ausente". */
    public function limiteFormatado(): string
    {
        return match ($this->limite_tipo) {
            'FAIXA' => Numero::decimal($this->limite_minimo).' a '.Numero::decimal($this->limite_maximo),
            'MAXIMO' => '≤ '.Numero::decimal($this->limite_maximo),
            'MINIMO' => '≥ '.Numero::decimal($this->limite_minimo),
            'AUSENCIA' => 'Ausente',
            default => 'Sem limite',
        };
    }

    public function nomeConformidade(): ?string
    {
        return $this->conformidade ? (self::CONFORMIDADES[$this->conformidade] ?? $this->conformidade) : null;
    }

    /** RN52: recalcula a conformidade com o limite copiado e o LQ do parâmetro. */
    public function calcularConformidade(): void
    {
        $this->conformidade = Conformidade::avaliar($this->limite_tipo, $this->limite_minimo, $this->limite_maximo,
            $this->valor, $this->menor_que_lq, $this->presenca, $this->parametro?->limite_quantificacao);
    }
}
