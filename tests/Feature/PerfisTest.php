<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Parametro;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC13 – Cadastrar perfis de usuário (RF25, RF26, RN23, RN24, RN26)
class PerfisTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Usuario::factory()->create(['nome' => 'Gerente']);
    }

    /** CT13: o coletor não acessa orçamentos, nem pelo endereço, e o menu não mostra a opção. */
    public function test_ct13_coletor_nao_acessa_orcamentos(): void
    {
        $coletor = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();
        $this->actingAs($coletor);

        $this->get('/orcamentos')->assertForbidden()->assertSee('Acesso negado')->assertSee(Perfis::MENSAGEM_ACESSO_NEGADO);
        $this->get('/orcamentos/create')->assertForbidden();
        $this->post('/orcamentos', [])->assertForbidden();

        $this->get('/')->assertOk()->assertDontSee(route('orcamentos.index'))->assertDontSee(route('usuarios.index'))
            ->assertSee(route('clientes.index'));
    }

    /** CT14: o único administrador ativo não perde o perfil Administrador. */
    public function test_ct14_ultimo_administrador_mantem_o_perfil(): void
    {
        $this->actingAs($this->admin);

        $this->put("/usuarios/{$this->admin->id}", ['nome' => 'Gerente', 'email' => $this->admin->email, 'perfis' => [Perfis::RECEPCAO]])
            ->assertSessionHasErrors('perfis');

        $this->assertSame([Perfis::ADMINISTRADOR], $this->admin->fresh()->listaPerfis());
    }

    public function test_cadastra_usuario_com_mais_de_um_perfil_e_registra_no_historico(): void
    {
        $this->actingAs($this->admin);

        $this->post('/usuarios', ['nome' => 'Ana', 'email' => 'ana@laboratorio.local', 'perfis' => [Perfis::ANALISTA, Perfis::RECEPCAO]])
            ->assertRedirect(route('usuarios.index'));

        $ana = Usuario::where('email', 'ana@laboratorio.local')->sole();
        $this->assertSame([Perfis::RECEPCAO, Perfis::ANALISTA], $ana->listaPerfis());
        $this->assertSame('Recepção, Analista', RegistroAuditoria::where(['registro_id' => $ana->id, 'campo' => 'perfis'])->value('valor_novo'));
    }

    public function test_usuario_sem_perfil_nao_e_gravado(): void
    {
        $this->actingAs($this->admin);

        $this->post('/usuarios', ['nome' => 'Ana', 'email' => 'ana@laboratorio.local', 'perfis' => []])
            ->assertSessionHasErrors(['perfis' => 'Marque ao menos um perfil.']);
        $this->post('/usuarios', ['nome' => 'Ana', 'email' => 'ana@laboratorio.local', 'perfis' => ['GERENTE']])
            ->assertSessionHasErrors('perfis.0');

        $this->assertDatabaseMissing('usuarios', ['email' => 'ana@laboratorio.local']);
    }

    public function test_altera_perfis_de_outro_usuario_e_bloqueia_os_proprios(): void
    {
        $this->actingAs($this->admin);
        $outro = Usuario::factory()->create();

        $this->put("/usuarios/{$outro->id}", ['nome' => $outro->nome, 'email' => $outro->email, 'perfis' => [Perfis::COLETOR]])
            ->assertRedirect(route('usuarios.index'));
        $this->assertSame([Perfis::COLETOR], $outro->fresh()->listaPerfis());

        $registro = RegistroAuditoria::where(['registro_id' => $outro->id, 'campo' => 'perfis'])->sole();
        $this->assertSame(['Administrador', 'Coletor'], [$registro->valor_anterior, $registro->valor_novo]);

        // UC13 3c: no próprio cadastro os perfis aparecem bloqueados.
        $this->get("/usuarios/{$this->admin->id}/edit")->assertOk()->assertSee('Você não pode alterar os próprios perfis');
        $this->put("/usuarios/{$this->admin->id}", ['nome' => 'Gerente', 'email' => $this->admin->email,
            'perfis' => [Perfis::ADMINISTRADOR, Perfis::ANALISTA]])->assertSessionHasErrors('perfis');
    }

    public function test_com_dois_administradores_um_pode_inativar_o_outro(): void
    {
        $this->actingAs(Usuario::factory()->create());

        $this->patch("/usuarios/{$this->admin->id}/situacao", ['ativo' => '0'])->assertSessionHas('sucesso');
        $this->assertFalse($this->admin->fresh()->ativo);
    }

    /** RN25: na implantação, os usuários já cadastrados recebem o perfil Administrador. */
    public function test_rn25_migracao_da_perfil_administrador_aos_usuarios_existentes(): void
    {
        $migracao = require database_path('migrations/2026_10_21_000000_create_usuario_perfis_table.php');
        $migracao->down();
        $migracao->up();

        $this->assertSame([Perfis::ADMINISTRADOR], $this->admin->fresh()->listaPerfis());
    }

    /** RN23: as permissões são a soma das permissões dos perfis. */
    public function test_permissoes_somam_os_perfis(): void
    {
        $coletorAnalista = Usuario::factory()->comPerfis(Perfis::COLETOR, Perfis::ANALISTA)->create();

        $this->assertTrue($coletorAnalista->can('coletas.registrar'));
        $this->assertTrue($coletorAnalista->can('amostras.receber'));
        $this->assertTrue($coletorAnalista->veTodasAsColetas());
        $this->assertFalse($coletorAnalista->can('orcamentos'));
        $this->assertFalse(Usuario::factory()->comPerfis(Perfis::COLETOR)->create()->veTodasAsColetas());
    }

    /** Matriz 7.4: recepção cadastra clientes e consulta o catálogo; o analista só consulta os dois. */
    public function test_consulta_e_alteracao_do_catalogo_e_dos_clientes(): void
    {
        $cliente = Cliente::factory()->create();
        $parametro = Parametro::factory()->create();

        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $this->get('/catalogo/parametros')->assertOk()->assertDontSee('Novo parâmetro')->assertDontSee(route('catalogo.parametros.edit', $parametro));
        $this->get("/catalogo/parametros/{$parametro->id}/edit")->assertForbidden();
        $this->get("/catalogo/parametros/{$parametro->id}/historico")->assertOk();
        $this->get('/clientes/create')->assertOk();
        $this->get('/configuracoes')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();

        $this->actingAs(Usuario::factory()->comPerfis(Perfis::ANALISTA)->create());
        $this->get("/clientes/{$cliente->id}")->assertOk()->assertDontSee(route('clientes.edit', $cliente));
        $this->get("/clientes/{$cliente->id}?aba=orcamentos")->assertOk()->assertDontSee('Novo orçamento');
        $this->get("/clientes/{$cliente->id}/edit")->assertForbidden();
        $this->get("/catalogo/parametros/{$parametro->id}/historico")->assertForbidden();
    }
}
