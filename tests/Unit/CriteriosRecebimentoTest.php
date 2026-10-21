<?php

namespace Tests\Unit;

use App\Support\CriteriosRecebimento;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

// RN35 – critérios de recebimento da amostra
class CriteriosRecebimentoTest extends TestCase
{
    public function test_tempo_desde_a_coleta(): void
    {
        $minutos = CriteriosRecebimento::minutosDesdeColeta(Carbon::parse('2026-10-14 09:20'), Carbon::parse('2026-10-14 12:30'));

        $this->assertSame(190, $minutos);
        $this->assertSame('3 h 10 min', CriteriosRecebimento::duracao($minutos));
        $this->assertSame('45 min', CriteriosRecebimento::duracao(45));
    }

    public function test_dentro_dos_criterios_nao_ha_alerta(): void
    {
        $this->assertSame([], CriteriosRecebimento::alertas(24 * 60, '10.0', 24, '10.0'));
        $this->assertSame([], CriteriosRecebimento::alertas(60, null, 24, '10.0'));
    }

    public function test_prazo_e_temperatura_fora_dos_criterios(): void
    {
        $alertas = CriteriosRecebimento::alertas(26 * 60 + 5, '12.0', 24, '10.0');

        $this->assertSame([
            'Prazo excedido: 26 h 05 min desde a coleta (máximo de 24 h).',
            'Temperatura acima do máximo configurado (10 °C).',
        ], $alertas);
    }
}
