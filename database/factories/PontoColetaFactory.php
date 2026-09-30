<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\PontoColeta;
use App\Models\TipoAmostra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PontoColeta>
 */
class PontoColetaFactory extends Factory
{
    protected $model = PontoColeta::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'identificacao' => 'Reservatório '.fake()->unique()->bothify('Bloco ?#'),
            'tipo_amostra_id' => TipoAmostra::factory(),
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->buildingNumber(),
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ];
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
