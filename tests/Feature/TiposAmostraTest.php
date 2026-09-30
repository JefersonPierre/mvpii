<?php

namespace Tests\Feature;

use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC05 1a – Cadastrar tipos de amostra (RF12, RN05, RN08)
class TiposAmostraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create());
    }

    public function test_cadastra_edita_e_lista(): void
    {
        $this->post(route('catalogo.tipos-amostra.store'), ['nome' => 'Água potável', 'descricao' => 'Consumo humano'])
            ->assertRedirect(route('catalogo.tipos-amostra.index'));
        $tipo = TipoAmostra::sole();

        $this->put(route('catalogo.tipos-amostra.update', $tipo), ['nome' => 'Água potável', 'descricao' => 'Para consumo humano']);

        $this->get(route('catalogo.tipos-amostra.index'))->assertOk()->assertSee('Para consumo humano');
        $this->get(route('catalogo.tipos-amostra.historico', $tipo))->assertOk()->assertSee('Consumo humano')->assertSee('Alteração');
    }

    public function test_lista_em_ordem_alfabetica_com_acentos(): void
    {
        TipoAmostra::factory()->create(['nome' => 'Efluente']);
        TipoAmostra::factory()->create(['nome' => 'Água potável']);

        $this->get(route('catalogo.tipos-amostra.index'))->assertSeeInOrder(['Água potável', 'Efluente']);
    }

    public function test_rn05_nome_unico_sem_diferenciar_maiusculas(): void
    {
        TipoAmostra::factory()->create(['nome' => 'Efluente']);

        $this->post(route('catalogo.tipos-amostra.store'), ['nome' => 'EFLUENTE'])
            ->assertSessionHasErrors(['nome' => 'Já existe um tipo de amostra com este nome.']);
        $this->assertSame(1, TipoAmostra::count());
    }

    public function test_inativa_e_reativa(): void
    {
        $tipo = TipoAmostra::factory()->create();

        $this->patch(route('catalogo.tipos-amostra.situacao', $tipo), ['ativo' => '0']);
        $this->assertFalse($tipo->fresh()->ativo);
        $this->patch(route('catalogo.tipos-amostra.situacao', $tipo), ['ativo' => '1']);
        $this->assertTrue($tipo->fresh()->ativo);

        $this->assertSame(['INATIVACAO', 'REATIVACAO'], RegistroAuditoria::where('entidade', 'TIPO_AMOSTRA')
            ->orderBy('id')->pluck('acao')->all());
    }
}
