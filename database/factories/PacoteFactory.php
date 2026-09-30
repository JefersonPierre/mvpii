<?php

namespace Database\Factories;

use App\Models\Pacote;
use App\Models\TipoAmostra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pacote>
 */
class PacoteFactory extends Factory
{
    protected $model = Pacote::class;

    public function definition(): array
    {
        return [
            'nome' => 'Pacote '.fake()->unique()->bothify('??-##'),
            'tipo_amostra_id' => TipoAmostra::factory(),
            'preco' => '150.00',
        ];
    }
}
