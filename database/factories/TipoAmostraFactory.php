<?php

namespace Database\Factories;

use App\Models\TipoAmostra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoAmostra>
 */
class TipoAmostraFactory extends Factory
{
    protected $model = TipoAmostra::class;

    public function definition(): array
    {
        return ['nome' => 'Amostra '.fake()->unique()->word()];
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
