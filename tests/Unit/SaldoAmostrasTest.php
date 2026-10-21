<?php

namespace Tests\Unit;

use App\Services\AgendamentoColetas;
use PHPUnit\Framework\TestCase;

// RN28: a quantidade de amostras de cada item não pode passar da quantidade aprovada no orçamento.
class SaldoAmostrasTest extends TestCase
{
    public function test_dentro_do_saldo_nao_ha_excedentes(): void
    {
        $this->assertSame([], AgendamentoColetas::excedentes([10 => 2, 11 => 2], [[10, 11], [10, 11]]));
    }

    /** CT17: pacote × 2 e 3 amostras do pacote. */
    public function test_acima_do_saldo_devolve_o_item_e_a_quantidade_pedida(): void
    {
        $this->assertSame([10 => 3], AgendamentoColetas::excedentes([10 => 2, 11 => 4], [[10, 11], [10, 11], [10]]));
    }

    public function test_item_repetido_na_mesma_amostra_conta_uma_vez(): void
    {
        $this->assertSame([], AgendamentoColetas::excedentes([10 => 1], [[10, 10]]));
    }

    public function test_item_fora_do_orcamento_ou_sem_saldo_e_excedente(): void
    {
        $this->assertSame([99 => 1, 10 => 1], AgendamentoColetas::excedentes([10 => 0], [[99, 10]]));
    }
}
