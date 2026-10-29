<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aparelho em que o coletor usa o sistema sem conexão (RN39, RNF16). Guarda só o hash do token.
 *
 * @property int $id
 * @property int $usuario_id
 * @property string $nome
 * @property Carbon $expira_em
 * @property Carbon|null $ultimo_uso_em
 * @property Carbon|null $revogado_em
 */
class Dispositivo extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    /** RN39: o acesso pelo aparelho vale por 12 horas. */
    public const VALIDADE_HORAS = 12;

    protected function casts(): array
    {
        return ['expira_em' => 'datetime', 'ultimo_uso_em' => 'datetime', 'revogado_em' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function valido(): bool
    {
        return $this->revogado_em === null && $this->expira_em->isFuture();
    }

    public static function porToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))->first();
    }
}
