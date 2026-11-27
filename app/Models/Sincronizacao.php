<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Registro de coleta enviado pelo celular (RF41), aceito uma única vez pelo identificador gerado no aparelho (RN40).
 * Se a coleta mudou enquanto o coletor estava sem conexão, fica como conflito até a Recepção decidir (RN42, RN43).
 *
 * @property int $id
 * @property string $uuid
 * @property int $coleta_id
 * @property int $usuario_id
 * @property string $origem
 * @property array $dados
 * @property string $situacao
 * @property string|null $motivo_conflito
 * @property array|null $resposta
 * @property Carbon $recebida_em
 */
class Sincronizacao extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'sincronizacoes';

    public const APLICADA = 'APLICADA';

    public const CONFLITO = 'CONFLITO';

    public const ACEITA = 'ACEITA';

    public const DESCARTADA = 'DESCARTADA';

    public const SITUACOES = [
        self::APLICADA => 'Aplicada',
        self::CONFLITO => 'Conflito pendente',
        self::ACEITA => 'Aceita',
        self::DESCARTADA => 'Descartada',
    ];

    protected function casts(): array
    {
        return ['dados' => 'array', 'resposta' => 'array', 'recebida_em' => 'datetime', 'resolvida_em' => 'datetime'];
    }

    public function coleta(): BelongsTo
    {
        return $this->belongsTo(Coleta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function resolvidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resolvida_por_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoAmostra::class);
    }

    public function nomeSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }
}
