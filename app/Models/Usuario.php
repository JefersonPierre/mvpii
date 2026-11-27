<?php

namespace App\Models;

use App\Support\Perfis;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Usuário do sistema (RF01–RF03), com um ou mais perfis (RF25).
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

    /** RNF16: aparelhos conectados ao aplicativo de campo. */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class)->orderByDesc('id');
    }

    public function perfis(): HasMany
    {
        return $this->hasMany(UsuarioPerfil::class);
    }

    /**
     * Códigos dos perfis, na ordem de Perfis::NOMES.
     *
     * @return list<string>
     */
    public function listaPerfis(): array
    {
        return Perfis::normalizar($this->perfis->pluck('perfil'));
    }

    /** Verdadeiro se o usuário tem ao menos um dos perfis informados. */
    public function temPerfil(string ...$perfis): bool
    {
        return array_intersect($this->listaPerfis(), $perfis) !== [];
    }

    public function nomesPerfis(): string
    {
        return Perfis::nomes($this->listaPerfis());
    }

    /** RN36: quem só tem o perfil Coletor vê apenas as coletas e amostras atribuídas a ele. */
    public function veTodasAsColetas(): bool
    {
        return $this->temPerfil(...Perfis::VEEM_TODAS_AS_COLETAS);
    }

    /**
     * Substitui os perfis do usuário. Chame dentro de uma transação; as regras (RN24) ficam com quem chama.
     *
     * @param  iterable<string>  $perfis
     */
    public function definirPerfis(iterable $perfis): void
    {
        $perfis = Perfis::normalizar($perfis);
        $this->perfis()->whereNotIn('perfil', $perfis)->delete();
        foreach (array_diff($perfis, $this->perfis()->pluck('perfil')->all()) as $perfil) {
            $this->perfis()->create(['perfil' => $perfil]);
        }
        $this->unsetRelation('perfis');
    }

    /**
     * Usuários ativos com o perfil informado, ex.: os coletores do agendamento (RN29).
     *
     * @param  Builder<Usuario>  $query
     */
    public function scopeAtivosComPerfil(Builder $query, string $perfil): void
    {
        $query->where('ativo', true)->whereHas('perfis', fn ($q) => $q->where('perfil', $perfil));
    }
}
