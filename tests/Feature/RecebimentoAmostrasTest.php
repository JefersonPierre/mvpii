<?php

namespace Tests\Feature;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Services\RegistroColetas;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\DadosDeColeta;
use Tests\TestCase;

// UC17 – Registrar recebimento da amostra (RF34, RN28, RN33, RN34, RN35)
class RecebimentoAmostrasTest extends TestCase
{
    use DadosDeColeta, RefreshDatabase;

    private Coleta $coleta;

    private Usuario $analista;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $this->criarDadosDeColeta();
        $this->coleta = $this->agendar([$this->reservatorio, $this->portaria], ['data' => today()->toDateString()]);
        $this->analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create(['nome' => 'Ana Analista']);
    }

    /** O coletor (ou outro usuário) registra a coleta das duas amostras há duas horas. */
    private function registrarColeta(?Usuario $quem = null): void
    {
        $this->actingAs($quem ?? $this->coletor);
        app(RegistroColetas::class)->registrar($this->coleta, [
            'data_hora_coleta' => now()->subHours(2)->format('Y-m-d H:i'),
            'amostras' => $this->coleta->amostras->mapWithKeys(fn (Amostra $a) => [$a->id => ['situacao' => 'COLETADA', 'temperatura' => '5']])->all(),
        ]);
        $this->actingAs($this->analista);
    }

    private function dados(array $alterar = []): array
    {
        return [
            'amostras' => [$this->coleta->amostras[0]->id],
            'recebida_em' => now()->format('Y-m-d\TH:i'),
            'temperatura_recebimento' => '6,0',
            'frasco_integro' => '1', 'identificacao_legivel' => '1', 'volume_suficiente' => '1', 'preservacao_adequada' => '1',
            'decisao' => 'ACEITAR',
            ...$alterar,
        ];
    }

    /** CT22: amostra Coletada dentro do prazo e da temperatura → Recebida. */
    public function test_ct22_aceita_amostra_dentro_dos_criterios(): void
    {
        $this->registrarColeta();
        $amostra = $this->coleta->amostras[0];

        $this->get('/amostras/receber')->assertOk()->assertSee($amostra->codigo)->assertSee('2 h 00 min');
        $this->get('/amostras/receber?codigo='.strtolower($amostra->codigo))->assertOk()->assertSee('Temperatura no recebimento');
        $this->post('/amostras/receber', $this->dados())->assertRedirect(route('amostras.receber'))->assertSessionHasNoErrors();

        $amostra->refresh();
        $this->assertSame([Amostra::RECEBIDA, $this->analista->id, '6.0', false, true],
            [$amostra->situacao, $amostra->recebida_por_id, $amostra->temperatura_recebimento, $amostra->fora_criterios, $amostra->frasco_integro]);
        $this->assertSame(1, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['recebidas']);
    }

    /** CT23: usuário com perfis Coletor e Analista não recebe a amostra cuja coleta ele registrou (RN34). */
    public function test_ct23_separacao_de_funcoes(): void
    {
        $coletorAnalista = Usuario::factory()->comPerfis(Perfis::COLETOR, Perfis::ANALISTA)->create();
        $this->coleta->update(['coletor_id' => $coletorAnalista->id]);
        $this->registrarColeta($coletorAnalista);
        $this->actingAs($coletorAnalista);

        $this->get('/amostras/receber')->assertSee('Você registrou a coleta desta amostra');
        $this->post('/amostras/receber', $this->dados())->assertSessionHasErrors('amostras');
        $this->assertSame(Amostra::COLETADA, $this->coleta->amostras[0]->fresh()->situacao);
    }

    /** CT24: rejeitar sem motivo não grava; com motivo, a amostra fica Rejeitada e volta ao saldo. */
    public function test_ct24_rejeicao_exige_motivo_e_devolve_o_saldo(): void
    {
        $this->registrarColeta();

        $this->post('/amostras/receber', $this->dados(['decisao' => 'REJEITAR']))
            ->assertSessionHasErrors(['motivo_rejeicao' => 'Informe o motivo da rejeição.']);
        $this->assertSame(Amostra::COLETADA, $this->coleta->amostras[0]->fresh()->situacao);

        $this->post('/amostras/receber', $this->dados(['decisao' => 'REJEITAR', 'motivo_rejeicao' => 'FRASCO_DANIFICADO', 'frasco_integro' => '0']))
            ->assertSessionHasNoErrors();
        $amostra = $this->coleta->amostras[0]->fresh();
        $this->assertSame([Amostra::REJEITADA, 'FRASCO_DANIFICADO', false], [$amostra->situacao, $amostra->motivo_rejeicao, $amostra->frasco_integro]);
        $this->assertSame(1, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['saldo']);
    }

    /** RN35: fora do prazo ou da temperatura, o aceite exige observação e fica marcado. */
    public function test_rn35_fora_dos_criterios_exige_observacao(): void
    {
        $this->registrarColeta();

        $this->post('/amostras/receber', $this->dados(['temperatura_recebimento' => '12']))
            ->assertSessionHasErrors(['observacoes_recebimento' => 'Fora dos critérios de recebimento: registre uma observação para aceitar.']);
        $this->post('/amostras/receber', $this->dados(['temperatura_recebimento' => '12', 'observacoes_recebimento' => 'Gelo derretido; aceita pela gerente.']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->coleta->amostras[0]->fresh()->fora_criterios);
    }

    /** UC17 1a e 2a: recebimento em conjunto; código inexistente ou amostra que não está Coletada. */
    public function test_recebimento_em_conjunto_e_amostra_nao_coletada(): void
    {
        $this->actingAs($this->analista);
        $this->get('/amostras/receber?codigo=AM-1999-00001')->assertSee('Amostra AM-1999-00001 não encontrada.');
        $prevista = $this->coleta->amostras[0];
        $this->get('/amostras/receber?codigo='.$prevista->codigo)->assertSee("A amostra {$prevista->codigo} está Prevista");
        $this->post('/amostras/receber', $this->dados())->assertSessionHasErrors('amostras');

        $this->registrarColeta();
        $this->post('/amostras/receber', $this->dados(['amostras' => $this->coleta->amostras->pluck('id')->all()]))->assertSessionHasNoErrors();
        $this->assertSame([Amostra::RECEBIDA, Amostra::RECEBIDA], $this->coleta->amostras()->pluck('situacao')->all());
    }

    /** Matriz 7.4: Recepção e Coletor não registram recebimento. */
    public function test_somente_analista_e_administrador_recebem(): void
    {
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $this->get('/amostras/receber')->assertForbidden();
        $this->actingAs($this->coletor);
        $this->post('/amostras/receber', $this->dados())->assertForbidden();
    }
}
