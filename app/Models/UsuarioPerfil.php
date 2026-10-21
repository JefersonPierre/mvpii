<?php

namespace App\Models;

use App\Support\Perfis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perfil de um usuário (RF25). Um usuário pode ter vários (RN23).
 *
 * @property string $perfil
 */
class UsuarioPerfil extends Model
{
    public $timestamps = false;

    protected $table = 'usuario_perfis';

    protected $fillable = ['perfil'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function nome(): string
    {
        return Perfis::NOMES[$this->perfil] ?? $this->perfil;
    }
}
