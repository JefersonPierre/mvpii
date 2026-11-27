<?php

namespace Tests\Feature;

use App\Models\Coleta;
use App\Models\PontoColeta;
use App\Models\Usuario;
use App\Services\Roteirizacao;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\DadosDeResultados;
use Tests\TestCase;

// MOD11 – UC20: roteiro das coletas do dia (RF44–RF47, RN47, RN48)
class RoteiroColetasTest extends TestCase
{
    use DadosDeResultados, RefreshDatabase;

    /** @var array<string, Coleta> */
    private array $coletas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCatalogoComLimites(6); // laboratório em -22.9056, -47.0608
        $this->reservatorio->update(['latitude' => -22.9556, 'longitude' => -47.0608]); // ≈ 5,5 km
        $this->portaria->update(['latitude' => -22.9156, 'longitude' => -47.0608]); // ≈ 1,1 km
        $semMapa = PontoColeta::factory()->create(['cliente_id' => $this->cliente->id, 'tipo_amostra_id' => $this->agua->id,
            'identificacao' => 'Poço artesiano', 'latitude' => null, 'longitude' => null]);

        Auth::setUser(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        foreach (['longe' => $this->reservatorio, 'sem' => $semMapa, 'perto' => $this->portaria] as $chave => $ponto) {
            $this->coletas[$chave] = $this->agendar([$ponto], ['data' => today()->toDateString(), 'horario' => null]);
        }
        Auth::logout();
    }

    public function test_ct31_ordem_sugerida_pelo_mais_proximo_e_sem_coordenadas_no_fim(): void
    {
        $ordem = app(Roteirizacao::class)->coletasDoDia($this->coletor->id, today()->toDateString())->modelKeys();

        $this->assertSame([$this->coletas['perto']->id, $this->coletas['longe']->id, $this->coletas['sem']->id], $ordem);
        $this->actingAs($this->coletor)->get(route('coletas.roteiro'))->assertOk()
            ->assertSeeInOrder(['Torneira da portaria', 'Reservatório superior', 'Poço artesiano']);
    }

    public function test_ct32_link_do_google_maps_com_origem_no_laboratorio_e_pontos_na_ordem(): void
    {
        $links = app(Roteirizacao::class)->links(app(Roteirizacao::class)->coletasDoDia($this->coletor->id, today()->toDateString()));

        $this->assertCount(1, $links['google']);
        parse_str(parse_url($links['google'][0], PHP_URL_QUERY), $q);
        $this->assertSame('-22.9056,-47.0608', $q['origin']);
        $this->assertSame('-22.9156,-47.0608', $q['waypoints']);
        $this->assertSame('-22.9556,-47.0608', $q['destination']);
        $this->assertStringContainsString('ll=-22.9156,-47.0608', $links['waze']);
    }

    public function test_rn47_ordem_alterada_manualmente_vale_para_o_dia(): void
    {
        $this->actingAs($this->coletor)->post(route('coletas.ordem', $this->coletas['longe']), ['direcao' => 'subir'])->assertRedirect();

        $ordem = app(Roteirizacao::class)->coletasDoDia($this->coletor->id, today()->toDateString())->modelKeys();
        $this->assertSame($this->coletas['longe']->id, $ordem[0]);
    }

    public function test_rn36_coletor_ve_so_o_proprio_roteiro_e_analista_nao_ordena(): void
    {
        $outro = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();
        $this->actingAs($outro)->get(route('coletas.roteiro', ['coletor' => $this->coletor->id]))->assertOk()
            ->assertDontSee('Torneira da portaria');
        $this->actingAs($outro)->post(route('coletas.ordem', $this->coletas['longe']), ['direcao' => 'subir'])->assertForbidden();

        $analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create();
        $this->actingAs($analista)->post(route('coletas.ordem', $this->coletas['longe']), ['direcao' => 'subir'])->assertForbidden();
    }
}
