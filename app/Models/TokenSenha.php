<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Link de criação de senha enviado por e-mail (RF02 e convite de novo usuário). */
class TokenSenha extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = null;

    protected $table = 'tokens_senha';

    protected $fillable = ['usuario_id', 'token_hash', 'expira_em', 'usado_em'];

    protected function casts(): array
    {
        return ['expira_em' => 'datetime', 'usado_em' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
