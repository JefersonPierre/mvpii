<?php

namespace Database\Factories;

use App\Models\Usuario;
use App\Support\Perfis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'senha' => 'Senha1234',
            'ativo' => true,
        ];
    }

    /** Sem perfis informados, o usuário é Administrador, como os usuários do Entregável 1 (RN25). */
    public function configure(): static
    {
        return $this->afterCreating(function (Usuario $usuario) {
            if (! $usuario->perfis()->exists()) {
                $usuario->definirPerfis([Perfis::ADMINISTRADOR]);
            }
        });
    }

    public function comPerfis(string ...$perfis): static
    {
        return $this->afterCreating(fn (Usuario $usuario) => $usuario->definirPerfis($perfis));
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
