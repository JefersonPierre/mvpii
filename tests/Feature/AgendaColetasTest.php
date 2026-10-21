<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\DadosDeColeta;
use Tests\TestCase;

// UC15 – Consultar agenda de coletas (RF31, RF32, RN36)
class AgendaColetasTest extends TestCase
{
    use DadosDeColeta, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $this->criarDadosDeColeta(quantidade: 5);
    }

    /** CT19: o coletor A vê na agenda da semana somente as coletas atribuídas a ele. */
    public function test_ct19_coletor_ve_somente_as_proprias_coletas(): void
    {
        $outro = Usuario::factory()->comPerfis(Perfis::COLETOR)->create(['nome' => 'Bruno Outro']);
        $data = today()->addDays(1);
        $minha = $this->agendar([$this->reservatorio], ['data' => $data->toDateString()]);
        $deOutro = $this->agendar([$this->portaria], ['data' => $data->toDateString(), 'coletor_id' => $outro->id, 'periodo' => 'TARDE']);

        $this->actingAs($this->coletor);
        $this->get('/coletas?data='.$data->toDateString())->assertOk()
            ->assertSee(route('coletas.show', $minha))->assertDontSee(route('coletas.show', $deOutro))
            ->assertSee('somente as coletas atribuídas a você');
        $this->get("/coletas?coletor={$outro->id}&data=".$data->toDateString())->assertDontSee(route('coletas.show', $deOutro));

        $this->get(route('coletas.show', $minha))->assertOk()->assertDontSee('ORC-');
        $this->get(route('coletas.show', $deOutro))->assertForbidden();
        $this->get(route('coletas.ficha', $deOutro))->assertForbidden();
    }

    public function test_agenda_por_dia_e_semana_com_filtros(): void
    {
        $data = today()->addDays(1);
        $coleta = $this->agendar([$this->reservatorio], ['data' => $data->toDateString()]);

        $this->get('/coletas?data='.$data->toDateString())->assertOk()->assertSee('Residencial Primavera')->assertSee('1 amostra');
        $this->get('/coletas?visao=dia&data='.$data->toDateString())->assertOk()->assertSee(route('coletas.show', $coleta));
        $this->get('/coletas?visao=dia&data='.$data->copy()->addDay()->toDateString())->assertSee('Nenhuma coleta neste dia');
        $this->get('/coletas?cliente=outro&data='.$data->toDateString())->assertDontSee(route('coletas.show', $coleta));
        $this->get('/coletas?situacao=CANCELADA&data='.$data->toDateString())->assertDontSee(route('coletas.show', $coleta));
    }

    /** RF32: a ficha de coleta é um PDF. */
    public function test_ficha_de_coleta_em_pdf(): void
    {
        $coleta = $this->agendar([$this->reservatorio, $this->portaria]);

        $this->get(route('coletas.ficha', $coleta))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
