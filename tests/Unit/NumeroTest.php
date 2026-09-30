<?php

namespace Tests\Unit;

use App\Support\Numero;
use PHPUnit\Framework\TestCase;

class NumeroTest extends TestCase
{
    public function test_le_numeros_digitados_no_formato_brasileiro(): void
    {
        $this->assertSame('1234.56', Numero::ler('1.234,56'));
        $this->assertSame('150.00', Numero::ler('R$ 150,00'));
        $this->assertSame('6.0', Numero::ler('6,0'));
        $this->assertSame('0.5', Numero::ler('0.5'));
        $this->assertSame('-22.9', Numero::ler('-22,9'));
        $this->assertNull(Numero::ler('  '));
        $this->assertSame('abc', Numero::ler('abc'));
    }

    public function test_exibe_numeros_no_formato_brasileiro(): void
    {
        $this->assertSame('6', Numero::decimal('6.000000'));
        $this->assertSame('0,0005', Numero::decimal('0.000500'));
        $this->assertSame('1.234,5', Numero::decimal(1234.5));
        $this->assertSame('-0,5', Numero::decimal(-0.5));
        $this->assertSame('', Numero::decimal(null));
        $this->assertSame('R$ 1.234,50', Numero::moeda('1234.5'));
        $this->assertSame('150,00', Numero::campo('150', true));
    }
}
