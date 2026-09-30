<?php

namespace Tests\Unit;

use App\Services\Auditoria;
use App\Support\RegrasAcesso;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RegrasAcessoTest extends TestCase
{
    public function test_senha_precisa_de_8_caracteres_com_letras_e_numeros(): void
    {
        $this->assertTrue(RegrasAcesso::senhaAtendeRegra('Senha123'));
        $this->assertFalse(RegrasAcesso::senhaAtendeRegra('Senha12'));
        $this->assertFalse(RegrasAcesso::senhaAtendeRegra('somenteletras'));
        $this->assertFalse(RegrasAcesso::senhaAtendeRegra('12345678'));
    }

    public function test_quinta_falha_bloqueia_por_15_minutos_e_zera_o_contador(): void
    {
        $agora = CarbonImmutable::parse('2026-09-30 10:00');

        $quarta = RegrasAcesso::registrarFalha(3, $agora);
        $this->assertSame(4, $quarta['tentativas_falhas']);
        $this->assertFalse($quarta['bloqueou']);

        $quinta = RegrasAcesso::registrarFalha(4, $agora);
        $this->assertTrue($quinta['bloqueou']);
        $this->assertSame(0, $quinta['tentativas_falhas']);
        $this->assertEquals($agora->addMinutes(15), $quinta['bloqueado_ate']);
    }

    public function test_bloqueio_vale_ate_o_horario_marcado(): void
    {
        $agora = CarbonImmutable::parse('2026-09-30 10:00');
        $this->assertTrue(RegrasAcesso::estaBloqueado($agora->addMinute(), $agora));
        $this->assertFalse(RegrasAcesso::estaBloqueado($agora->subMinute(), $agora));
        $this->assertFalse(RegrasAcesso::estaBloqueado(null, $agora));
    }

    public function test_auditoria_registra_so_os_campos_que_mudaram(): void
    {
        $diferencas = Auditoria::diferencas(
            ['nome' => 'Maria', 'email' => 'a@b.com', 'ativo' => true],
            ['nome' => 'Maria', 'email' => 'c@d.com', 'ativo' => false],
            ['nome', 'email', 'ativo'],
        );

        $this->assertSame([
            ['campo' => 'email', 'valor_anterior' => 'a@b.com', 'valor_novo' => 'c@d.com'],
            ['campo' => 'ativo', 'valor_anterior' => 'true', 'valor_novo' => 'false'],
        ], $diferencas);
    }
}
