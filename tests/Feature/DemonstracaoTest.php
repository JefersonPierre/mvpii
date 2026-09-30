<?php

namespace Tests\Feature;

use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Publicação de demonstração (LAB_DEMONSTRACAO): e-mails simulados, com o link na tela, e dados de exemplo.
class DemonstracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_na_tela_so_na_demonstracao(): void
    {
        config(['mail.default' => 'log']);
        $usuario = Usuario::factory()->create();

        $this->post('/recuperar-senha', ['email' => $usuario->email])->assertSessionMissing('link_local');

        config(['laboratorio.demonstracao' => true]);

        $this->post('/recuperar-senha', ['email' => $usuario->email])->assertSessionHas('link_local');
    }

    public function test_preparar_carrega_dados_iniciais_uma_unica_vez(): void
    {
        config(['laboratorio.demonstracao' => true]);

        $this->artisan('sistema:preparar')->assertSuccessful();
        $this->assertDatabaseCount('usuarios', 1);
        $this->assertDatabaseHas('pacotes', ['ativo' => true]);
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('orcamentos', ['situacao' => Orcamento::RASCUNHO, 'valor_total' => 380]);
        $this->assertDatabaseCount('orcamento_itens', 1);

        // O que for inativado depois não volta a ser ativado no próximo início do servidor.
        Pacote::query()->update(['ativo' => false]);
        $this->artisan('sistema:preparar')->assertSuccessful();
        $this->assertDatabaseMissing('pacotes', ['ativo' => true]);
        $this->assertDatabaseCount('orcamentos', 1);
    }
}
