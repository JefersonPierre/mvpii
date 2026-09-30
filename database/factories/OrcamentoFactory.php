<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\TipoAmostra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Orçamento simples, sem itens. Para testar regras do fluxo, prefira criar pelo formulário (POST /orcamentos).
 *
 * @extends Factory<Orcamento>
 */
class OrcamentoFactory extends Factory
{
    protected $model = Orcamento::class;

    private static int $sequencia = 0;

    public function definition(): array
    {
        return [
            'ano' => (int) now()->format('Y'),
            'sequencia' => ++self::$sequencia + 1000,
            'revisao' => 1,
            'cliente_id' => Cliente::factory(),
            'tipo_amostra_id' => TipoAmostra::factory(),
            'validade_dias' => 30,
            'valor_total' => '100.00',
        ];
    }

    /** Enviado há alguns dias, com a validade informada. */
    public function enviado(string $validoAte): static
    {
        return $this->state(fn () => [
            'situacao' => Orcamento::ENVIADO,
            'data_envio' => now()->subDays(5),
            'valido_ate' => $validoAte,
        ]);
    }
}
