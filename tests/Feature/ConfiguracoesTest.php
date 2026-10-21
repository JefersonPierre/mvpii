<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC12 – Configurar parâmetros comerciais (RF22, RN08, RN14)
class ConfiguracoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create(['nome' => 'Gerente']));
    }

    public function test_exibe_os_valores_iniciais(): void
    {
        $this->get(route('configuracoes'))->assertOk()
            ->assertSee('value="30"', false)->assertSee('50,00')->assertSee('value="15"', false);
    }

    public function test_altera_as_configuracoes_com_historico(): void
    {
        $this->put(route('configuracoes'), [
            'validade_dias' => '15', 'taxa_coleta' => '60,00', 'desconto_maximo' => '12,5', 'condicoes_comerciais' => 'À vista.',
            'prazo_recebimento_horas' => '24', 'temperatura_maxima_recebimento' => '10',
        ])->assertRedirect(route('configuracoes'));

        $config = Configuracao::atual();
        $this->assertSame([15, '60.00', '12.50', 'À vista.'],
            [$config->validade_dias, $config->taxa_coleta, $config->desconto_maximo, $config->condicoes_comerciais]);
        $this->get(route('configuracoes'))->assertSee('R$ 60,00')->assertSee('12,5%')->assertSee('Gerente');
    }

    /** UC19 – critérios de recebimento (RF35, RN35). */
    public function test_uc19_altera_os_criterios_de_recebimento(): void
    {
        $this->put(route('configuracoes'), [
            'validade_dias' => '30', 'taxa_coleta' => '50,00', 'desconto_maximo' => '15',
            'prazo_recebimento_horas' => '30', 'temperatura_maxima_recebimento' => '8,5',
        ])->assertRedirect(route('configuracoes'));

        $config = Configuracao::atual();
        $this->assertSame([30, '8.5'], [$config->prazo_recebimento_horas, $config->temperatura_maxima_recebimento]);
        $this->get(route('configuracoes'))->assertSee('8,5 °C');
    }

    public function test_uc19_4a_criterios_zerados_ou_negativos(): void
    {
        $this->put(route('configuracoes'), [
            'validade_dias' => '30', 'taxa_coleta' => '50,00', 'desconto_maximo' => '15',
            'prazo_recebimento_horas' => '0', 'temperatura_maxima_recebimento' => '-2',
        ])->assertSessionHasErrors([
            'prazo_recebimento_horas' => 'O prazo deve ser de pelo menos 1 hora.',
            'temperatura_maxima_recebimento' => 'A temperatura máxima deve ser maior que zero.',
        ]);
        $this->assertSame(24, Configuracao::atual()->prazo_recebimento_horas);
    }

    public function test_uc12_4a_valores_invalidos(): void
    {
        $this->put(route('configuracoes'), ['validade_dias' => '0', 'taxa_coleta' => '-1', 'desconto_maximo' => '101'])
            ->assertSessionHasErrors([
                'validade_dias' => 'A validade deve ser de pelo menos 1 dia.',
                'taxa_coleta',
                'desconto_maximo' => 'O desconto máximo não pode passar de 100%.',
            ]);
        $this->assertSame(30, Configuracao::atual()->validade_dias);
    }
}
