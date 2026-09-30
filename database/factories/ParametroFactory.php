<?php

namespace Database\Factories;

use App\Models\Parametro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parametro>
 */
class ParametroFactory extends Factory
{
    protected $model = Parametro::class;

    public function definition(): array
    {
        return [
            'nome' => 'Parâmetro '.fake()->unique()->bothify('??-##'),
            'unidade' => 'mg/L',
            'categoria' => 'FISICO_QUIMICA',
            'preco' => '40.00',
        ];
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
