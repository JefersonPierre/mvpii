<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC01 – Autenticar usuário (RF01, RN09, RN10)
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_entra_com_email_e_senha_corretos(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'gerente@laboratorio.local']);

        $this->post('/login', ['email' => 'Gerente@Laboratorio.local', 'senha' => 'Senha1234'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);

        // Próxima requisição: o usuário é recarregado da sessão pelo provider configurado em config/auth.php.
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk()->assertSee('Olá, '.$usuario->nome);
    }

    public function test_senha_errada_informa_a_tentativa(): void
    {
        Usuario::factory()->create(['email' => 'gerente@laboratorio.local']);

        $this->post('/login', ['email' => 'gerente@laboratorio.local', 'senha' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos. Tentativa 1 de 5; na quinta o acesso fica bloqueado por 15 minutos.']);

        $this->assertGuest();
    }

    public function test_bloqueia_apos_cinco_tentativas_mesmo_com_a_senha_certa(): void
    {
        $usuario = Usuario::factory()->create();

        foreach (range(1, 5) as $_) {
            $this->post('/login', ['email' => $usuario->email, 'senha' => 'errada']);
        }
        $this->assertNotNull($usuario->fresh()->bloqueado_ate);

        $this->post('/login', ['email' => $usuario->email, 'senha' => 'Senha1234'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(16)->minutes();
        $this->post('/login', ['email' => $usuario->email, 'senha' => 'Senha1234']);
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_usuario_inativo_nao_entra(): void
    {
        $usuario = Usuario::factory()->inativo()->create();

        $this->post('/login', ['email' => $usuario->email, 'senha' => 'Senha1234'])
            ->assertSessionHasErrors(['email' => 'Este usuário está inativo. Procure o responsável pelo sistema no laboratório.']);
        $this->assertGuest();
    }

    public function test_telas_internas_exigem_login(): void
    {
        $this->get('/usuarios')->assertRedirect(route('login'));
    }

    public function test_usuario_inativado_durante_a_sessao_e_desconectado(): void
    {
        $usuario = Usuario::factory()->create();
        $this->actingAs($usuario);
        $usuario->update(['ativo' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
