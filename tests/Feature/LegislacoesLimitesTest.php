<?php

namespace Tests\Feature;

use App\Models\Legislacao;
use App\Models\Limite;
use App\Models\Parametro;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC06 – Cadastrar legislação e limites (RF15, RF16, RN06, RN07, RN08)
class LegislacoesLimitesTest extends TestCase
{
    use RefreshDatabase;

    private TipoAmostra $agua;

    private TipoAmostra $efluente;

    private Parametro $ph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create());
        $this->agua = TipoAmostra::factory()->create(['nome' => 'Água potável']);
        $this->efluente = TipoAmostra::factory()->create(['nome' => 'Efluente']);
        $this->ph = Parametro::factory()->create(['nome' => 'pH', 'unidade' => 'adimensional']);
    }

    private function portaria888(): Legislacao
    {
        $this->post(route('catalogo.legislacoes.store'), [
            'nome' => 'Portaria GM/MS nº 888/2021', 'orgao_emissor' => 'Ministério da Saúde',
            'inicio_vigencia' => '2021-05-04', 'tipos_amostra' => [$this->agua->id],
        ]);

        return Legislacao::where('nome', 'Portaria GM/MS nº 888/2021')->sole();
    }

    private function limite(array $dados): array
    {
        return ['parametro_id' => $this->ph->id, 'tipo_amostra_id' => $this->agua->id, 'tipo' => 'FAIXA',
            'valor_minimo' => '6,0', 'valor_maximo' => '9,0', 'observacao' => '', ...$dados];
    }

    public function test_ct07_cadastra_faixa_de_ph_para_agua_potavel(): void
    {
        $portaria = $this->portaria888();

        $this->post(route('catalogo.limites.store', $portaria), $this->limite([]))
            ->assertRedirect(route('catalogo.legislacoes.show', $portaria));

        $limite = Limite::sole();
        $this->assertEquals([6.0, 9.0], [(float) $limite->valor_minimo, (float) $limite->valor_maximo]);
        $this->get(route('catalogo.legislacoes.show', $portaria))->assertOk()
            ->assertSee('Vigente desde 04/05/2021')->assertSeeInOrder(['pH', 'Água potável', 'Faixa', '6', '9']);
        $this->get(route('catalogo.legislacoes.historico', $portaria))->assertSee('Limite pH / Água potável')->assertSee('Faixa: 6 a 9 adimensional');
    }

    public function test_rn06_faixa_com_minimo_maior_ou_igual_ao_maximo(): void
    {
        $portaria = $this->portaria888();

        $this->post(route('catalogo.limites.store', $portaria), $this->limite(['valor_minimo' => '5', 'valor_maximo' => '2']))
            ->assertSessionHasErrors(['valor_minimo' => 'O valor mínimo deve ser menor que o máximo.']);
        $this->post(route('catalogo.limites.store', $portaria), $this->limite(['valor_minimo' => '2', 'valor_maximo' => '2']))
            ->assertSessionHasErrors('valor_minimo');
    }

    public function test_rn06_um_limite_por_parametro_legislacao_e_tipo(): void
    {
        $portaria = $this->portaria888();
        $this->post(route('catalogo.limites.store', $portaria), $this->limite([]));

        $this->post(route('catalogo.limites.store', $portaria), $this->limite(['tipo' => 'MAXIMO', 'valor_maximo' => '8']))
            ->assertSessionHasErrors(['parametro_id' => 'Já existe limite de pH para água potável nesta legislação.']);
        $this->assertSame(1, Limite::count());
    }

    public function test_tipo_do_limite_define_os_valores_gravados(): void
    {
        $portaria = $this->portaria888();

        $this->post(route('catalogo.limites.store', $portaria), $this->limite(['tipo' => 'AUSENCIA', 'observacao' => 'Ausência em 100 mL']));
        $limite = Limite::sole();
        $this->assertNull($limite->valor_minimo);
        $this->assertNull($limite->valor_maximo);

        $this->put(route('catalogo.limites.update', $limite), $this->limite(['tipo' => 'MAXIMO', 'valor_minimo' => '', 'valor_maximo' => '']))
            ->assertSessionHasErrors(['valor_maximo' => 'Informe o valor máximo.']);
    }

    public function test_limite_so_para_tipos_de_amostra_da_legislacao(): void
    {
        $portaria = $this->portaria888();

        $this->post(route('catalogo.limites.store', $portaria), $this->limite(['tipo_amostra_id' => $this->efluente->id]))
            ->assertSessionHasErrors(['tipo_amostra_id' => 'O tipo de amostra não é abrangido por esta legislação.']);
    }

    public function test_nao_desmarca_tipo_de_amostra_que_tem_limites(): void
    {
        $portaria = $this->portaria888();
        $this->post(route('catalogo.limites.store', $portaria), $this->limite([]));

        $this->put(route('catalogo.legislacoes.update', $portaria), [
            'nome' => $portaria->nome, 'orgao_emissor' => 'Ministério da Saúde', 'inicio_vigencia' => '2021-05-04',
            'tipos_amostra' => [$this->efluente->id],
        ])->assertSessionHasErrors('tipos_amostra');
    }

    public function test_rn07_substituicao_encerra_a_anterior_e_copia_os_limites(): void
    {
        $anterior = $this->portaria888();
        $this->post(route('catalogo.limites.store', $anterior), $this->limite([]));

        $this->post(route('catalogo.legislacoes.store'), [
            'nome' => 'Portaria GM/MS nº 999/2026', 'orgao_emissor' => 'Ministério da Saúde', 'inicio_vigencia' => '2026-01-01',
            'tipos_amostra' => [$this->agua->id], 'substitui_id' => $anterior->id, 'fim_anterior' => '2025-12-31', 'copiar_limites' => '1',
        ]);
        $nova = Legislacao::where('nome', 'Portaria GM/MS nº 999/2026')->sole();

        $anterior->refresh();
        $this->assertSame('2025-12-31', $anterior->fim_vigencia->toDateString());
        $this->assertSame($nova->id, $anterior->substituida_por_id);
        $this->assertSame(1, $anterior->limites()->count(), 'Os limites da anterior são mantidos.');
        $this->assertSame(1, $nova->limites()->count(), 'Os limites foram copiados.');
        $this->assertSame('Portaria GM/MS nº 999/2026',
            RegistroAuditoria::where(['entidade' => 'LEGISLACAO', 'registro_id' => $anterior->id, 'campo' => 'substituida_por'])->value('valor_novo'));

        // RN07: a anterior, encerrada, fica só para consulta.
        $this->get(route('catalogo.legislacoes.show', $anterior))->assertSee('Encerrada em 31/12/2025')->assertDontSee('Adicionar limite');
        $this->post(route('catalogo.limites.store', $anterior), $this->limite(['parametro_id' => Parametro::factory()->create()->id]))
            ->assertSessionHas('erro');
        $this->delete(route('catalogo.limites.destroy', $anterior->limites()->first()))->assertSessionHas('erro');
        $this->assertSame(1, $anterior->limites()->count());
    }

    public function test_substituida_precisa_terminar_antes_do_inicio_da_nova(): void
    {
        $anterior = $this->portaria888();

        $this->post(route('catalogo.legislacoes.store'), [
            'nome' => 'Nova', 'orgao_emissor' => 'MS', 'inicio_vigencia' => '2026-01-01', 'tipos_amostra' => [$this->agua->id],
            'substitui_id' => $anterior->id, 'fim_anterior' => '2026-02-01',
        ])->assertSessionHasErrors(['fim_anterior' => 'A legislação substituída deve terminar antes do início da nova.']);
    }

    public function test_remove_limite_com_registro_no_historico(): void
    {
        $portaria = $this->portaria888();
        $this->post(route('catalogo.limites.store', $portaria), $this->limite([]));

        $this->delete(route('catalogo.limites.destroy', Limite::sole()))->assertSessionHas('sucesso', 'Limite removido.');

        $this->assertSame(0, Limite::count());
        $remocao = RegistroAuditoria::where(['entidade' => 'LEGISLACAO', 'campo' => 'Limite pH / Água potável'])->latest('id')->first();
        $this->assertNull($remocao->valor_novo);
        $this->assertSame('Faixa: 6 a 9 adimensional', $remocao->valor_anterior);
    }
}
