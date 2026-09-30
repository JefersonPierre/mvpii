<?php

namespace Tests\Feature;

use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC05 – parâmetros (RF13) e pacotes (RF14), RN04, RN05, RN08
class ParametrosPacotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create());
    }

    public function test_cadastra_parametro_com_preco_no_formato_brasileiro(): void
    {
        $this->post(route('catalogo.parametros.store'), [
            'nome' => 'Turbidez', 'unidade' => 'uT', 'metodo' => 'SMWW 2130 B', 'limite_quantificacao' => '0,1',
            'categoria' => 'FISICO_QUIMICA', 'preco' => '1.234,50',
        ])->assertRedirect(route('catalogo.parametros.index'));

        $parametro = Parametro::sole();
        $this->assertSame('1234.50', $parametro->preco);
        $this->assertEquals(0.1, (float) $parametro->limite_quantificacao);
        $this->get(route('catalogo.parametros.index'))->assertSee('Turbidez')->assertSee('R$ 1.234,50');
        $this->get(route('catalogo.parametros.historico', $parametro))->assertSee('R$ 1.234,50')->assertSee('Físico-química');
    }

    public function test_rn05_nome_unico_unidade_e_preco_obrigatorios(): void
    {
        Parametro::factory()->create(['nome' => 'Turbidez']);

        $this->post(route('catalogo.parametros.store'), ['nome' => 'TURBIDEZ', 'unidade' => '', 'categoria' => 'FISICO_QUIMICA', 'preco' => ''])
            ->assertSessionHasErrors([
                'nome' => 'Já existe um parâmetro com este nome.',
                'unidade' => 'Informe a unidade de medida.',
                'preco' => 'Informe o preço.',
            ]);
    }

    public function test_cadastra_pacote_com_parametros_e_historico(): void
    {
        $agua = TipoAmostra::factory()->create(['nome' => 'Água potável']);
        $ph = Parametro::factory()->create(['nome' => 'pH']);
        $cloro = Parametro::factory()->create(['nome' => 'Cloro residual livre']);

        $this->post(route('catalogo.pacotes.store'), [
            'nome' => 'Potabilidade básica', 'tipo_amostra_id' => $agua->id, 'parametros' => [$ph->id, $cloro->id], 'preco' => '150,00',
        ])->assertRedirect(route('catalogo.pacotes.index'));

        $pacote = Pacote::sole();
        $this->assertSame(['Cloro residual livre', 'pH'], $pacote->parametros->pluck('nome')->all());

        $this->put(route('catalogo.pacotes.update', $pacote), [
            'nome' => 'Potabilidade básica', 'tipo_amostra_id' => $agua->id, 'parametros' => [$ph->id], 'preco' => '120,00',
        ]);
        $alteracoes = RegistroAuditoria::where(['entidade' => 'PACOTE', 'acao' => 'ALTERACAO'])->pluck('valor_novo', 'campo');
        $this->assertSame('pH', $alteracoes['parametros']);
        $this->assertSame('120.00', $alteracoes['preco']);
    }

    public function test_pacote_exige_parametros_ativos(): void
    {
        $agua = TipoAmostra::factory()->create();
        $inativo = Parametro::factory()->inativo()->create();

        $this->post(route('catalogo.pacotes.store'), ['nome' => 'Vazio', 'tipo_amostra_id' => $agua->id, 'parametros' => [], 'preco' => '10'])
            ->assertSessionHasErrors(['parametros' => 'Selecione os parâmetros do pacote.']);
        $this->post(route('catalogo.pacotes.store'), ['nome' => 'Com inativo', 'tipo_amostra_id' => $agua->id, 'parametros' => [$inativo->id], 'preco' => '10'])
            ->assertSessionHasErrors(['parametros.0' => 'O pacote só pode incluir parâmetros ativos.']);
    }

    public function test_pacote_mantem_parametro_que_foi_inativado_depois(): void
    {
        $agua = TipoAmostra::factory()->create();
        $ph = Parametro::factory()->create();
        $pacote = Pacote::factory()->create(['tipo_amostra_id' => $agua->id]);
        $pacote->parametros()->attach($ph);
        $ph->update(['ativo' => false]);

        $this->put(route('catalogo.pacotes.update', $pacote), [
            'nome' => $pacote->nome, 'tipo_amostra_id' => $agua->id, 'parametros' => [$ph->id], 'preco' => '99,90',
        ])->assertSessionHasNoErrors();
    }
}
