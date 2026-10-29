<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// MOD10 – UC21: aplicativo do coletor instalável e com funcionamento sem conexão (RF38, RF40, RNF14). O registro sem
// conexão (CT25) roda no navegador; aqui fica a parte do servidor: a tela guardada no aparelho e o service worker.
class AplicativoCampoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ct25_tela_do_coletor_e_service_worker_para_uso_sem_conexao(): void
    {
        $coletor = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();

        $this->actingAs($coletor)->get(route('campo'))->assertOk()
            ->assertSee('Coletas do dia')->assertSee('manifest.webmanifest', false)->assertSee('data-url-conectar', false);

        $sw = $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/');
        $this->assertStringStartsWith('application/javascript', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString("const TELA = '/campo'", $sw->getContent());
        $this->assertStringContainsString('/manifest.webmanifest', $sw->getContent());

        $manifesto = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('/campo', $manifesto['start_url']);
        $this->assertSame('standalone', $manifesto['display']);
    }

    public function test_rn36_so_quem_registra_coletas_abre_o_aplicativo(): void
    {
        $analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create();

        $this->actingAs($analista)->get(route('campo'))->assertForbidden();
    }
}
