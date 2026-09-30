<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'tipo_pessoa' => 'J',
            'nome' => 'Condomínio '.fake()->unique()->lastName(),
            'documento' => fake()->unique()->cnpj(false),
            'cep' => '13010000',
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->buildingNumber(),
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ];
    }

    public function interessado(): static
    {
        return $this->state(['interessado' => true, 'documento' => null, 'cep' => null, 'logradouro' => null,
            'numero' => null, 'bairro' => null, 'cidade' => null, 'uf' => null]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }

    /** Cria o cliente já com um contato principal (RN02). */
    public function configure(): static
    {
        return $this->afterCreating(function (Cliente $cliente) {
            $cliente->contatos()->create([
                'nome' => fake()->name(),
                'telefone' => '1932320000',
                'email' => fake()->safeEmail(),
                'principal' => true,
            ]);
        });
    }
}
