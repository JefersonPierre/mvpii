<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Usuário do sistema (RF01–RF03).
 *
 * @property int $id
 * @property string $nome
 * @property string $email
 * @property bool $ativo
 * @property int $tentativas_falhas
 * @property Carbon|null $bloqueado_ate
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'usuarios';

    /** Coluna da senha usada pelo login do Laravel. */
    protected $authPasswordName = 'senha';

    protected $fillable = ['nome', 'email', 'senha', 'ativo'];

    protected $hidden = ['senha', 'remember_token'];

    protected $attributes = ['ativo' => true, 'tentativas_falhas' => 0];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'ativo' => 'boolean',
            'bloqueado_ate' => 'datetime',
        ];
    }

    /** RN11: o e-mail é guardado sempre em minúsculas para a unicidade valer sem diferenciar maiúsculas. */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $valor) => mb_strtolower(trim($valor)));
    }

    public function tokensSenha(): HasMany
    {
        return $this->hasMany(TokenSenha::class);
    }
}
