<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC03 – Cadastrar cliente e UC07 – Consultar clientes (RF04–RF08, RN01, RN02, RN04, RN08)
class ClientesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create(['nome' => 'Recepção']));
    }

    /** Dados de um cliente completo pessoa jurídica, como o formulário envia. */
    private function dados(array $alterar = []): array
    {
        return array_replace_recursive([
            'cadastro' => 'cliente',
            'tipo_pessoa' => 'J',
            'nome' => 'Condomínio Residencial Primavera',
            'nome_fantasia' => '',
            'documento' => '11.222.333/0001-81',
            'cep' => '13010-000',
            'logradouro' => 'Rua Barão de Jaguara',
            'numero' => '100',
            'complemento' => '',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
            'observacoes' => '',
            'contatos' => [
                'n1' => ['nome' => 'Ana Síndica', 'cargo' => 'Síndica', 'telefone' => '(19) 99876-5432', 'email' => ''],
            ],
            'principal' => 'n1',
        ], $alterar);
    }

    public function test_ct03_cadastra_pessoa_juridica_e_exibe_a_ficha(): void
    {
        $resposta = $this->post('/clientes', $this->dados());

        $cliente = Cliente::sole();
        $resposta->assertRedirect(route('clientes.show', $cliente));
        $this->assertSame('11222333000181', $cliente->documento);
        $this->assertSame('13010000', $cliente->cep);
        $this->assertFalse($cliente->interessado);
        $this->assertSame('19998765432', $cliente->contatoPrincipal->telefone);

        $this->get(route('clientes.show', $cliente))->assertOk()
            ->assertSee('Condomínio Residencial Primavera')->assertSee('11.222.333/0001-81');
    }

    public function test_ct04_cpf_invalido_nao_grava(): void
    {
        $this->post('/clientes', $this->dados(['tipo_pessoa' => 'F', 'documento' => '529.982.247-24']))
            ->assertSessionHasErrors(['documento' => 'CPF inválido: confira os dígitos.']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_ct04_documento_duplicado_oferece_abrir_o_existente(): void
    {
        $existente = Cliente::factory()->create(['documento' => '11222333000181', 'nome' => 'Indústria Alfa']);

        $this->post('/clientes', $this->dados())
            ->assertSessionHasErrors(['documento' => 'CNPJ já cadastrado.'])
            ->assertSessionHas('cliente_existente', ['id' => $existente->id, 'nome' => 'Indústria Alfa']);

        $this->assertSame(1, Cliente::count());
    }

    public function test_aceita_cnpj_alfanumerico(): void
    {
        $this->post('/clientes', $this->dados(['documento' => '12.abc.345/01de-35']))->assertSessionHasNoErrors();

        $this->assertSame('12ABC34501DE35', Cliente::sole()->documento);
    }

    public function test_ct05_cadastro_rapido_de_interessado_sem_documento(): void
    {
        $this->post('/clientes', [
            'cadastro' => 'interessado',
            'tipo_pessoa' => 'F',
            'nome' => 'João da Silva',
            'contatos' => ['n1' => ['nome' => 'João da Silva', 'email' => 'joao@exemplo.com']],
            'principal' => 'n1',
        ])->assertSessionHasNoErrors();

        $interessado = Cliente::sole();
        $this->assertTrue($interessado->interessado);
        $this->assertNull($interessado->documento);
    }

    public function test_cliente_completo_exige_documento_e_endereco(): void
    {
        $this->post('/clientes', $this->dados(['documento' => '', 'cep' => '', 'cidade' => '']))
            ->assertSessionHasErrors(['documento', 'cep', 'cidade']);
    }

    public function test_rn02_contato_precisa_de_telefone_ou_email(): void
    {
        $this->post('/clientes', $this->dados(['contatos' => ['n1' => ['telefone' => '']]]))
            ->assertSessionHasErrors(['contatos.n1.telefone' => 'Informe o telefone ou o e-mail do contato.']);
    }

    public function test_rn02_exige_ao_menos_um_contato_e_um_principal(): void
    {
        $this->post('/clientes', $this->dados(['contatos' => ['n1' => ['nome' => '', 'cargo' => '', 'telefone' => '']]]))
            ->assertSessionHasErrors(['contatos' => 'Adicione ao menos um contato com telefone ou e-mail.']);

        $this->post('/clientes', $this->dados(['principal' => 'x']))
            ->assertSessionHasErrors(['principal' => 'Marque o contato principal.']);
    }

    public function test_edicao_de_contatos_fica_no_historico(): void
    {
        $this->post('/clientes', $this->dados([
            'contatos' => ['n2' => ['nome' => 'Zé Zelador', 'telefone' => '1932320000']],
        ]));
        $cliente = Cliente::sole();
        [$ana, $ze] = [$cliente->contatos->firstWhere('nome', 'Ana Síndica'), $cliente->contatos->firstWhere('nome', 'Zé Zelador')];

        // Troca o telefone da Ana e remove o Zé (a linha dele não é enviada).
        $this->put(route('clientes.update', $cliente), $this->dados([
            'contatos' => [
                'n1' => null,
                "c{$ana->id}" => ['id' => $ana->id, 'nome' => 'Ana Síndica', 'telefone' => '19911112222'],
            ],
            'principal' => "c{$ana->id}",
        ]))->assertRedirect(route('clientes.show', $cliente));

        $cliente->refresh();
        $this->assertSame(['Ana Síndica'], $cliente->contatos->pluck('nome')->all());
        $this->assertSame('19911112222', $cliente->contatoPrincipal->telefone);
        $this->assertNull($ze->fresh());

        $historico = RegistroAuditoria::where('registro_id', $cliente->id)->where('acao', 'ALTERACAO')->get();
        $this->assertEqualsCanonicalizing(['Contato Ana Síndica', 'Contato Zé Zelador'], $historico->pluck('campo')->all());
        $this->assertNull($historico->firstWhere('campo', 'Contato Zé Zelador')->valor_novo);
    }

    public function test_inativa_e_reativa_com_historico(): void
    {
        $cliente = Cliente::factory()->create();

        $this->patch(route('clientes.situacao', $cliente), ['ativo' => '0'])->assertSessionHas('sucesso', 'Cliente inativado.');
        $this->assertFalse($cliente->fresh()->ativo);
        $this->patch(route('clientes.situacao', $cliente), ['ativo' => '1']);
        $this->assertTrue($cliente->fresh()->ativo);

        $this->get(route('clientes.show', [$cliente, 'aba' => 'historico']))->assertOk()
            ->assertSee('Inativação')->assertSee('Reativação')->assertSee('Recepção');
    }

    public function test_lista_em_ordem_alfabetica_do_portugues(): void
    {
        Cliente::factory()->create(['nome' => 'Zeta Indústria']);
        Cliente::factory()->create(['nome' => 'ecoLab Serviços']);
        Cliente::factory()->create(['nome' => 'Água Viva Condomínio']);
        Cliente::factory()->create(['nome' => 'Bela Vista']);

        $this->get('/clientes')->assertSeeInOrder(['Água Viva Condomínio', 'Bela Vista', 'ecoLab Serviços', 'Zeta Indústria']);
    }

    public function test_rf08_pesquisa_por_nome_documento_cidade_e_situacao(): void
    {
        Cliente::factory()->create(['nome' => 'Condomínio Primavera', 'documento' => '11222333000181', 'cidade' => 'Campinas']);
        Cliente::factory()->create(['nome' => 'Indústria Beta', 'cidade' => 'Sumaré']);
        Cliente::factory()->inativo()->create(['nome' => 'Escola Antiga', 'cidade' => 'Campinas']);
        Cliente::factory()->interessado()->create(['nome' => 'Residencial Verão']);

        $this->get('/clientes')->assertOk()
            ->assertSee('Condomínio Primavera')->assertSee('Residencial Verão')->assertSee('Interessado')
            ->assertDontSee('Escola Antiga');
        $this->get('/clientes?busca=primav')->assertSee('Condomínio Primavera')->assertDontSee('Indústria Beta');
        $this->get('/clientes?busca=11.222.333')->assertSee('Condomínio Primavera')->assertDontSee('Indústria Beta');
        $this->get('/clientes?cidade=Sumaré')->assertSee('Indústria Beta')->assertDontSee('Condomínio Primavera');
        $this->get('/clientes?situacao=inativos')->assertSee('Escola Antiga')->assertDontSee('Indústria Beta');
        $this->get('/clientes?busca=nada+assim')->assertSee('Nenhum cliente encontrado.');
    }
}
