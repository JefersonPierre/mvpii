<?php

namespace Database\Factories;

use App\Models\Legislacao;
use App\Models\Limite;
use App\Models\Parametro;
use App\Models\TipoAmostra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Limite>
 */
class LimiteFactory extends Factory
{
    protected $model = Limite::class;

    public function definition(): array
    {
        return [
            'legislacao_id' => Legislacao::factory(),
            'parametro_id' => Parametro::factory(),
            'tipo_amostra_id' => TipoAmostra::factory(),
            'tipo' => 'MAXIMO',
            'valor_maximo' => '5',
        ];
    }
}
