<?php

namespace Tests\Feature;

use App\Mail\LinkSenhaMail;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// RF02 – Recuperar senha por e-mail
class RecuperacaoSenhaTest extends TestCase
{
    use RefreshDatabase;

    private function pedirLink(Usuario $usuario): string
    {
        $link = null;
        Mail::fake();
        $this->post('/recuperar-senha', ['email' => $usuario->email])->assertSessionHas('sucesso');
        Mail::assertSent(LinkSenhaMail::class, function (LinkSenhaMail $mail) use (&$link) {
            $link = $mail->link;

            return $mail->tipo === LinkSenhaMail::RECUPERACAO;
        });
        parse_str(parse_url($link, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    public function test_cria_nova_senha_pelo_link_e_desbloqueia_o_acesso(): void
    {
        $usuario = Usuario::factory()->create();
        $usuario->forceFill(['bloqueado_ate' => now()->addMinutes(10)])->save();
        $token = $this->pedirLink($usuario);

        $this->post('/nova-senha', ['token' => $token, 'senha' => 'NovaSenha9', 'senha_confirmation' => 'NovaSenha9'])
            ->assertRedirect(route('login'));

        $usuario->refresh();
        $this->assertTrue(Hash::check('NovaSenha9', $usuario->getAuthPassword()));
        $this->assertNull($usuario->bloqueado_ate);
    }

    public function test_link_so_pode_ser_usado_uma_vez(): void
    {
        $token = $this->pedirLink(Usuario::factory()->create());
        $dados = ['token' => $token, 'senha' => 'NovaSenha9', 'senha_confirmation' => 'NovaSenha9'];

        $this->post('/nova-senha', $dados)->assertRedirect(route('login'));
        $this->post('/nova-senha', $dados)->assertSessionHasErrors(['senha' => 'Link inválido ou expirado. Solicite um novo.']);
    }

    public function test_link_expira_em_uma_hora(): void
    {
        $token = $this->pedirLink(Usuario::factory()->create());
        $this->travel(61)->minutes();

        $this->post('/nova-senha', ['token' => $token, 'senha' => 'NovaSenha9', 'senha_confirmation' => 'NovaSenha9'])
            ->assertSessionHasErrors('senha');
    }

    public function test_senha_fraca_e_recusada(): void
    {
        $token = $this->pedirLink(Usuario::factory()->create());

        $this->post('/nova-senha', ['token' => $token, 'senha' => 'curta', 'senha_confirmation' => 'curta'])
            ->assertSessionHasErrors(['senha' => 'A senha deve ter no mínimo 8 caracteres, com letras e números.']);
    }

    public function test_email_nao_cadastrado_recebe_a_mesma_resposta_sem_envio(): void
    {
        Mail::fake();

        $this->post('/recuperar-senha', ['email' => 'ninguem@laboratorio.local'])->assertSessionHas('sucesso');
        Mail::assertNothingSent();
    }
}
