<?php

namespace Tests\Feature;

use App\Models\Amostra;
use App\Models\Usuario;
use App\Services\RecebimentoAmostras;
use App\Services\RegistroColetas;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\DadosDeColeta;
use Tests\TestCase;

// UC18 – Consultar amostras (RF36, RF37, RN08, RN33, RN36)
class ConsultaAmostrasTest extends TestCase
{
    use DadosDeColeta, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create(['nome' => 'Rita Recepção']));
        $this->criarDadosDeColeta();
    }

    public function test_pesquisa_por_codigo_cliente_ponto_periodo_e_situacao(): void
    {
        $coleta = $this->agendar([$this->reservatorio, $this->portaria]);
        [$a, $b] = $coleta->amostras;

        $this->get('/amostras')->assertOk()->assertSee($a->codigo)->assertSee($b->codigo);
        $this->get('/amostras?codigo='.$a->codigo)->assertSee($a->codigo)->assertDontSee($b->codigo);
        $this->get('/amostras?ponto=portaria')->assertSee($b->codigo)->assertDontSee($a->codigo);
        $this->get('/amostras?cliente=outro')->assertSee('Nenhuma amostra encontrada.');
        $this->get('/amostras?situacao=RECEBIDA')->assertSee('Nenhuma amostra encontrada.');
        $this->get('/amostras?de='.today()->addDays(10)->toDateString())->assertSee('Nenhuma amostra encontrada.');
    }

    /** RF36 / RNF12: a linha do tempo mostra cada etapa com usuário, data e hora. */
    public function test_linha_do_tempo_do_agendamento_ao_recebimento(): void
    {
        $coleta = $this->agendar([$this->reservatorio], ['data' => today()->toDateString()]);
        $amostra = $coleta->amostras->first();
        $this->actingAs($this->coletor);
        app(RegistroColetas::class)->registrar($coleta, ['data_hora_coleta' => now()->subHour()->format('Y-m-d H:i'),
            'amostras' => [$amostra->id => ['situacao' => 'COLETADA', 'temperatura' => '7']]]);
        $this->actingAs($analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create(['nome' => 'Ana Analista']));
        app(RecebimentoAmostras::class)->registrar(collect([$amostra]), ['recebida_em' => now()->format('Y-m-d H:i'),
            'temperatura_recebimento' => '12', 'decisao' => 'REJEITAR', 'motivo_rejeicao' => 'TEMPERATURA']);

        $this->get(route('amostras.show', $amostra))->assertOk()
            ->assertSeeInOrder(['Agendada', 'Rita Recepção', 'Carlos Coletor', 'Coletada', 'Carlos Coletor', '7 °C',
                'Rejeitada', 'Ana Analista', '12 °C', 'Temperatura fora do limite']);
        $this->assertSame(Amostra::REJEITADA, $amostra->fresh()->situacao);
    }

    /** RN36 / UC18 1b: o coletor só vê as amostras das próprias coletas. */
    public function test_coletor_ve_somente_as_amostras_das_proprias_coletas(): void
    {
        $outro = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();
        $minha = $this->agendar([$this->reservatorio])->amostras->first();
        $deOutro = $this->agendar([$this->portaria], ['coletor_id' => $outro->id])->amostras->first();

        $this->actingAs($this->coletor);
        $this->get('/amostras')->assertSee($minha->codigo)->assertDontSee($deOutro->codigo);
        $this->get(route('amostras.show', $deOutro))->assertForbidden();
        $this->get(route('clientes.show', [$this->cliente, 'aba' => 'coletas']))->assertSee($minha->codigo)->assertDontSee($deOutro->codigo);
    }

    /** RF37 / UC18 1a: coletas e amostras na ficha do cliente e no orçamento. */
    public function test_coletas_na_ficha_do_cliente_e_no_orcamento(): void
    {
        $amostra = $this->agendar([$this->reservatorio])->amostras->first();

        $this->get(route('clientes.show', [$this->cliente, 'aba' => 'coletas']))->assertOk()->assertSee('Coletas (1)')->assertSee($amostra->codigo);
        $this->get(route('orcamentos.show', $this->orcamento))->assertOk()->assertSee($amostra->codigo)
            ->assertSee('Potabilidade básica 1/2');
    }

    /** Início de cada perfil: próximas coletas e amostras a receber. */
    public function test_inicio_mostra_as_pendencias_do_perfil(): void
    {
        $this->agendar([$this->reservatorio]);

        $this->actingAs($this->coletor);
        $this->get('/')->assertOk()->assertSee('Minhas próximas coletas (1)')->assertDontSee('Aguardando resposta do cliente');
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::ANALISTA)->create());
        $this->get('/')->assertOk()->assertSee('Amostras a receber (0)');
    }
}
