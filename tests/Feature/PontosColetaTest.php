<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PontoColeta;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC04 – Cadastrar ponto de coleta (RF09, RF10, RF11, RN03, RN04, RN08)
class PontosColetaTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private TipoAmostra $aguaPotavel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create());
        $this->cliente = Cliente::factory()->create(['nome' => 'Condomínio Primavera']);
        $this->aguaPotavel = TipoAmostra::factory()->create(['nome' => 'Água potável']);
    }

    private function dados(array $alterar = []): array
    {
        return [
            'identificacao' => 'Reservatório superior – Bloco A',
            'tipo_amostra_id' => $this->aguaPotavel->id,
            'cep' => '13010-000',
            'logradouro' => 'Rua Barão de Jaguara',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
            'referencia' => 'Cobertura, acesso pela escada B',
            'latitude' => '-22,9056',
            'longitude' => '-47,0608',
            ...$alterar,
        ];
    }

    public function test_ct06_cadastra_ponto_e_lista_na_ficha(): void
    {
        $this->post(route('pontos.store', $this->cliente), $this->dados())
            ->assertRedirect(route('clientes.show', [$this->cliente, 'aba' => 'pontos']));

        $ponto = PontoColeta::sole();
        $this->assertSame($this->cliente->id, $ponto->cliente_id);
        $this->assertSame('13010000', $ponto->cep);
        $this->assertEquals(-22.9056, (float) $ponto->latitude);

        $this->get(route('clientes.show', [$this->cliente, 'aba' => 'pontos']))->assertOk()
            ->assertSee('Reservatório superior – Bloco A')->assertSee('Água potável')->assertSee('Ver no mapa');
    }

    public function test_formulario_oferece_o_endereco_do_cliente(): void
    {
        $this->get(route('pontos.create', $this->cliente))->assertOk()
            ->assertSee('Usar endereço do cliente')
            ->assertSee('data-endereco-cliente', false)
            ->assertSee('13010-000');

        $interessado = Cliente::factory()->interessado()->create();
        $this->get(route('pontos.create', $interessado))->assertOk()
            ->assertSee('O cliente não tem endereço cadastrado para copiar.')
            ->assertDontSee('Usar endereço do cliente');
    }

    public function test_campos_obrigatorios_e_coordenadas(): void
    {
        $this->post(route('pontos.store', $this->cliente), $this->dados(['identificacao' => '', 'tipo_amostra_id' => '', 'cidade' => '']))
            ->assertSessionHasErrors(['identificacao', 'tipo_amostra_id', 'cidade']);

        $this->post(route('pontos.store', $this->cliente), $this->dados(['longitude' => '']))
            ->assertSessionHasErrors(['longitude' => 'Informe a longitude junto com a latitude.']);

        $this->post(route('pontos.store', $this->cliente), $this->dados(['latitude' => '95']))
            ->assertSessionHasErrors(['latitude' => 'A latitude deve estar entre -90 e 90.']);

        $this->assertSame(0, PontoColeta::count());
    }

    public function test_tipo_de_amostra_inativo_nao_pode_ser_escolhido(): void
    {
        $inativo = TipoAmostra::factory()->inativo()->create();

        $this->post(route('pontos.store', $this->cliente), $this->dados(['tipo_amostra_id' => $inativo->id]))
            ->assertSessionHasErrors(['tipo_amostra_id' => 'Selecione um tipo de amostra ativo.']);
    }

    public function test_cliente_inativo_nao_recebe_novos_pontos(): void
    {
        $this->cliente->update(['ativo' => false]);

        $this->post(route('pontos.store', $this->cliente), $this->dados())->assertSessionHas('erro');
        $this->assertSame(0, PontoColeta::count());
    }

    public function test_edicao_e_inativacao_ficam_no_historico_do_cliente(): void
    {
        $this->post(route('pontos.store', $this->cliente), $this->dados());
        $ponto = PontoColeta::sole();
        $efluente = TipoAmostra::factory()->create(['nome' => 'Efluente']);

        $this->put(route('pontos.update', $ponto), $this->dados(['tipo_amostra_id' => $efluente->id]));
        $this->patch(route('pontos.situacao', $ponto), ['ativo' => '0']);

        $this->assertFalse($ponto->fresh()->ativo);
        $alteracao = RegistroAuditoria::where(['entidade' => 'PONTO_COLETA', 'acao' => 'ALTERACAO'])->sole();
        $this->assertSame(['tipo_amostra', 'Água potável', 'Efluente'], [$alteracao->campo, $alteracao->valor_anterior, $alteracao->valor_novo]);

        $this->get(route('clientes.show', [$this->cliente, 'aba' => 'historico']))->assertOk()
            ->assertSee('Ponto Reservatório superior – Bloco A')->assertSee('Tipo de amostra')->assertSee('Inativação');
    }

    public function test_rn04_inativar_cliente_inativa_os_pontos(): void
    {
        $pontos = PontoColeta::factory()->count(2)->create(['cliente_id' => $this->cliente->id]);

        $this->patch(route('clientes.situacao', $this->cliente), ['ativo' => '0']);

        $pontos->each(fn ($p) => $this->assertFalse($p->fresh()->ativo));
        $this->assertSame(2, RegistroAuditoria::where(['entidade' => 'PONTO_COLETA', 'acao' => 'INATIVACAO'])->count());

        // O ponto só volta depois que o cliente for reativado.
        $this->patch(route('pontos.situacao', $pontos[0]), ['ativo' => '1'])->assertSessionHas('erro');
        $this->assertFalse($pontos[0]->fresh()->ativo);
    }
}
