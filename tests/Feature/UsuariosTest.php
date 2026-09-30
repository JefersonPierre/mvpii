<?php

namespace Tests\Feature;

use App\Mail\LinkSenhaMail;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// UC02 – Cadastrar usuários (RF03, RN08, RN10, RN11)
class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Usuario::factory()->create(['nome' => 'Administrador']);
        $this->actingAs($this->admin);
    }

    public function test_lista_com_busca_e_filtro_de_situacao(): void
    {
        Usuario::factory()->create(['nome' => 'Maria Souza', 'email' => 'maria@exemplo.com']);
        // E-mail fixo: um e-mail aleatório com "mar" faria a busca encontrar o João.
        Usuario::factory()->inativo()->create(['nome' => 'João Lima', 'email' => 'joao@exemplo.com']);

        $this->get('/usuarios')->assertOk()->assertSee('Maria Souza')->assertDontSee('João Lima');
        $this->get('/usuarios?situacao=inativos')->assertSee('João Lima')->assertDontSee('Maria Souza');
        $this->get('/usuarios?situacao=todos&busca=mar')->assertSee('Maria Souza')->assertDontSee('João Lima');
    }

    public function test_cadastra_usuario_envia_convite_e_registra_historico(): void
    {
        Mail::fake();

        $this->post('/usuarios', ['nome' => 'Maria Souza', 'email' => 'Maria@Laboratorio.local'])
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('sucesso');

        $maria = Usuario::where('email', 'maria@laboratorio.local')->firstOrFail();
        Mail::assertSent(LinkSenhaMail::class, fn ($m) => $m->tipo === LinkSenhaMail::CONVITE && $m->hasTo('maria@laboratorio.local'));
        $this->assertSame(3, RegistroAuditoria::where(['registro_id' => $maria->id, 'acao' => 'INCLUSAO'])->count());
    }

    public function test_email_duplicado_e_recusado(): void
    {
        Usuario::factory()->create(['email' => 'maria@laboratorio.local']);

        $this->post('/usuarios', ['nome' => 'Outra Maria', 'email' => 'MARIA@laboratorio.local'])
            ->assertSessionHasErrors(['email' => 'E-mail já cadastrado.']);
    }

    public function test_edicao_registra_valor_anterior_e_novo(): void
    {
        $maria = Usuario::factory()->create(['nome' => 'Maria', 'email' => 'maria@antigo.local']);

        $this->put("/usuarios/{$maria->id}", ['nome' => 'Maria', 'email' => 'maria@laboratorio.local'])
            ->assertRedirect(route('usuarios.index'));

        $registro = RegistroAuditoria::where('registro_id', $maria->id)->sole();
        $this->assertSame(['ALTERACAO', 'email', 'maria@antigo.local', 'maria@laboratorio.local', $this->admin->id], [
            $registro->acao, $registro->campo, $registro->valor_anterior, $registro->valor_novo, $registro->usuario_id,
        ]);

        $this->get("/usuarios/{$maria->id}/historico")->assertOk()->assertSee('maria@antigo.local')->assertSee('Alteração');
    }

    public function test_inativa_e_reativa_com_historico(): void
    {
        $maria = Usuario::factory()->create();

        $this->patch("/usuarios/{$maria->id}/situacao", ['ativo' => '0'])->assertSessionHas('sucesso', 'Usuário inativado.');
        $this->assertFalse($maria->fresh()->ativo);

        $this->patch("/usuarios/{$maria->id}/situacao", ['ativo' => '1'])->assertSessionHas('sucesso', 'Usuário reativado.');
        $this->assertTrue($maria->fresh()->ativo);

        $this->assertSame(['REATIVACAO', 'INATIVACAO'], RegistroAuditoria::where('registro_id', $maria->id)
            ->orderByDesc('id')->pluck('acao')->all());
    }

    public function test_nao_pode_inativar_a_si_mesmo(): void
    {
        $this->patch("/usuarios/{$this->admin->id}/situacao", ['ativo' => '0'])
            ->assertSessionHas('erro', 'Você não pode inativar o seu próprio usuário.');
        $this->assertTrue($this->admin->fresh()->ativo);
    }
}
