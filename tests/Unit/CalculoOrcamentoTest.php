<?php

namespace Tests\Unit;

use App\Support\CalculoOrcamento;
use PHPUnit\Framework\TestCase;

// RN13 – cálculo do orçamento
class CalculoOrcamentoTest extends TestCase
{
    public function test_ct08_pacote_parametro_taxa_e_desconto(): void
    {
        $r = CalculoOrcamento::calcular(
            [['preco' => '150.00', 'quantidade' => 2], ['preco' => '40.00', 'quantidade' => 2]],
            '50.00',
            '10',
        );

        $this->assertSame(['300.00', '80.00'], $r['subtotais']);
        $this->assertSame('380.00', $r['subtotal_itens']);
        $this->assertSame('43.00', $r['valor_desconto']);
        $this->assertSame('387.00', $r['valor_total']);
    }

    public function test_sem_desconto_e_sem_taxa(): void
    {
        $r = CalculoOrcamento::calcular([['preco' => '25.50', 'quantidade' => 3]], '0', '0');

        $this->assertSame('76.50', $r['valor_total']);
        $this->assertSame('0.00', $r['valor_desconto']);
    }

    public function test_arredonda_o_desconto_para_o_centavo(): void
    {
        // 33,33 × 1 = 33,33; 7,5% = 2,49975 → 2,50
        $r = CalculoOrcamento::calcular([['preco' => '33.33', 'quantidade' => 1]], '0', '7.5');

        $this->assertSame('2.50', $r['valor_desconto']);
        $this->assertSame('30.83', $r['valor_total']);
    }
}
