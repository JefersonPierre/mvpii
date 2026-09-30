<?php

namespace Database\Factories;

use App\Models\Legislacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Legislacao>
 */
class LegislacaoFactory extends Factory
{
    protected $model = Legislacao::class;

    public function definition(): array
    {
        return [
            'nome' => 'Portaria nº '.fake()->unique()->numerify('###/2021'),
            'orgao_emissor' => 'Ministério da Saúde',
            'inicio_vigencia' => '2021-05-04',
        ];
    }
}
