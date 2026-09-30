<?php

namespace Tests\Feature;

use App\Models\Pacote;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Publicação de demonstração (LAB_DEMONSTRACAO): e-mails simulados, com aviso e link na tela.
class DemonstracaoTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO = 'Ambiente de demonstração: os e-mails são simulados';

    public function test_aviso_e_link_na_tela_so_na_demonstracao(): void
    {
        config(['mail.default' => 'log']);
        $usuario = Usuario::factory()->create();

        $this->get('/login')->assertDontSee(self::AVISO);
        $this->post('/recuperar-senha', ['email' => $usuario->email])->assertSessionMissing('link_local');

        config(['laboratorio.demonstracao' => true]);

        $this->get('/login')->assertSee(self::AVISO);
        $this->post('/recuperar-senha', ['email' => $usuario->email])->assertSessionHas('link_local');
    }

    public function test_preparar_carrega_dados_iniciais_uma_unica_vez(): void
    {
        config(['laboratorio.demonstracao' => true]);

        $this->artisan('sistema:preparar')->assertSuccessful();
        $this->assertDatabaseCount('usuarios', 1);
        $this->assertDatabaseHas('pacotes', ['ativo' => true]);

        // O que for inativado depois não volta a ser ativado no próximo início do servidor.
        Pacote::query()->update(['ativo' => false]);
        $this->artisan('sistema:preparar')->assertSuccessful();
        $this->assertDatabaseMissing('pacotes', ['ativo' => true]);
    }
}
