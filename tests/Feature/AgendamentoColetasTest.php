<?php

namespace Tests\Feature;

use App\Mail\ColetaMail;
use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Orcamento;
use App\Models\PontoColeta;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\DadosDeColeta;
use Tests\TestCase;

// UC14 – Registrar agendamento de coleta (RF27–RF30, RN27–RN31, RN37)
class AgendamentoColetasTest extends TestCase
{
    use DadosDeColeta, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create(['nome' => 'Rita Recepção']));
    }

    /** CT15: 2 amostras de orçamento Aprovado → coleta Agendada, códigos AM-2026-… e e-mail ao contato principal. */
    public function test_ct15_agenda_coleta_gera_codigos_e_avisa_o_cliente(): void
    {
        $this->criarDadosDeColeta();

        $this->get("/orcamentos/{$this->orcamento->id}/coletas/create")->assertOk()->assertSee('Saldo de amostras do orçamento');
        $resposta = $this->post("/orcamentos/{$this->orcamento->id}/coletas", $this->dadosAgendamento([$this->reservatorio, $this->portaria]));

        $coleta = Coleta::sole();
        $resposta->assertRedirect(route('coletas.show', $coleta))->assertSessionHas('sucesso');
        $this->assertSame(Coleta::AGENDADA, $coleta->situacao);
        $ano = now()->format('Y');
        $this->assertSame(["AM-$ano-00001", "AM-$ano-00002"], $coleta->amostras->pluck('codigo')->all());
        $this->assertSame([Amostra::PREVISTA, Amostra::PREVISTA], $coleta->amostras->pluck('situacao')->all());
        $this->assertSame(2, $coleta->amostras->first()->itens()->count());

        $email = $this->cliente->contatoPrincipal->email;
        Mail::assertSent(ColetaMail::class, fn ($m) => $m->tipo === ColetaMail::CONFIRMACAO && $m->hasTo($email));
        $this->assertTrue(RegistroAuditoria::where(['entidade' => 'COLETA', 'registro_id' => $coleta->id, 'acao' => 'INCLUSAO'])->exists());

        $this->get(route('coletas.show', $coleta))->assertOk()->assertSee("AM-$ano-00001")->assertSee('Torneira da portaria');
    }

    /** CT16: orçamento Enviado (não aprovado) não origina coleta. */
    public function test_ct16_orcamento_nao_aprovado_e_recusado(): void
    {
        $this->criarDadosDeColeta(Orcamento::ENVIADO);

        $this->get("/orcamentos/{$this->orcamento->id}/coletas/create")->assertRedirect(route('orcamentos.show', $this->orcamento));
        $this->post("/orcamentos/{$this->orcamento->id}/coletas", $this->dadosAgendamento([$this->reservatorio]))
            ->assertSessionHasErrors(['orcamento' => 'Só é possível agendar coleta de orçamento Aprovado.']);
        $this->assertSame(0, Coleta::count());
    }

    /** CT17: pacote × 2 e 3 amostras do pacote → não grava e o saldo continua 2. */
    public function test_ct17_quantidade_acima_do_saldo_nao_grava(): void
    {
        $this->criarDadosDeColeta();

        $this->post("/orcamentos/{$this->orcamento->id}/coletas", $this->dadosAgendamento([$this->reservatorio, $this->portaria, $this->portaria]))
            ->assertSessionHasErrors('amostras');

        $this->assertSame(0, Amostra::count());
        $this->assertSame(2, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['saldo']);
        Mail::assertNothingSent();
    }

    /** CT18: cancelar coleta Agendada com motivo → coleta e amostras Canceladas e saldo devolvido. */
    public function test_ct18_cancela_coleta_e_devolve_o_saldo(): void
    {
        $this->criarDadosDeColeta();
        $coleta = $this->agendar([$this->reservatorio, $this->portaria]);
        $this->assertSame(0, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['saldo']);

        $this->post("/coletas/{$coleta->id}/cancelar", [])->assertSessionHasErrors(['motivo' => 'Informe o motivo do cancelamento.']);
        $this->post("/coletas/{$coleta->id}/cancelar", ['motivo' => 'Cliente pediu para adiar.'])->assertRedirect(route('coletas.show', $coleta));

        $this->assertSame(Coleta::CANCELADA, $coleta->fresh()->situacao);
        $this->assertSame([Amostra::CANCELADA, Amostra::CANCELADA], $coleta->amostras()->pluck('situacao')->all());
        $this->assertSame(2, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['saldo']);
        Mail::assertSent(ColetaMail::class, fn ($m) => $m->tipo === ColetaMail::CANCELAMENTO);

        // RN31: a coleta cancelada não muda mais.
        $this->post("/coletas/{$coleta->id}/cancelar", ['motivo' => 'De novo'])->assertSessionHas('erro');
        $this->get("/coletas/{$coleta->id}/edit")->assertRedirect(route('coletas.show', $coleta));
    }

    /** UC14 1a: reagendar mantém os códigos, exige motivo e avisa o cliente. */
    public function test_reagenda_mantendo_os_codigos(): void
    {
        $this->criarDadosDeColeta();
        $coleta = $this->agendar([$this->reservatorio]);
        $codigo = $coleta->amostras->first()->codigo;
        $novaData = today()->addDays(5)->toDateString();

        $this->put("/coletas/{$coleta->id}", ['data' => $novaData, 'periodo' => 'TARDE', 'coletor_id' => $this->coletor->id])
            ->assertSessionHasErrors(['motivo' => 'Informe o motivo do reagendamento.']);
        $this->put("/coletas/{$coleta->id}", ['data' => $novaData, 'periodo' => 'TARDE', 'coletor_id' => $this->coletor->id, 'motivo' => 'Chuva forte'])
            ->assertRedirect(route('coletas.show', $coleta));

        $coleta->refresh();
        $this->assertSame([$novaData, 'TARDE', 'Chuva forte', $codigo], [$coleta->data->toDateString(), $coleta->periodo, $coleta->motivo, $coleta->amostras->first()->codigo]);
        Mail::assertSent(ColetaMail::class, fn ($m) => $m->tipo === ColetaMail::REAGENDAMENTO);
    }

    /** RN27: o ponto deve ser ativo, do cliente do orçamento e do mesmo tipo de amostra. */
    public function test_rn27_ponto_de_outro_tipo_ou_cliente_e_recusado(): void
    {
        $this->criarDadosDeColeta();
        $efluente = PontoColeta::factory()->create(['cliente_id' => $this->cliente->id, 'tipo_amostra_id' => TipoAmostra::factory()->create()->id]);
        $outroCliente = PontoColeta::factory()->create(['tipo_amostra_id' => $this->agua->id]);
        $inativo = PontoColeta::factory()->inativo()->create(['cliente_id' => $this->cliente->id, 'tipo_amostra_id' => $this->agua->id]);

        foreach ([$efluente, $outroCliente, $inativo] as $ponto) {
            $this->post("/orcamentos/{$this->orcamento->id}/coletas", $this->dadosAgendamento([$ponto]))
                ->assertSessionHasErrors('amostras.0.ponto_coleta_id');
        }
        $this->assertSame(0, Coleta::count());
    }

    /** RN29: data no passado e coletor sem o perfil são recusados; coletor ocupado gera alerta e pede confirmação. */
    public function test_rn29_data_coletor_e_alerta_de_coletor_ocupado(): void
    {
        $this->criarDadosDeColeta(quantidade: 5);
        $analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create();
        $url = "/orcamentos/{$this->orcamento->id}/coletas";

        $this->post($url, $this->dadosAgendamento([$this->reservatorio], ['data' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors(['data' => 'A data da coleta não pode ser anterior a hoje.']);
        $this->post($url, $this->dadosAgendamento([$this->reservatorio], ['coletor_id' => $analista->id]))
            ->assertSessionHasErrors(['coletor_id' => 'Selecione um coletor ativo com o perfil Coletor.']);

        $this->agendar([$this->reservatorio]);
        $this->post($url, $this->dadosAgendamento([$this->portaria]))->assertSessionHas('conflito');
        $this->assertSame(1, Coleta::count());

        $this->post($url, $this->dadosAgendamento([$this->portaria], ['confirmar_conflito' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(2, Coleta::count());
    }

    /** RN37: contato principal sem e-mail → a coleta é agendada e o usuário é avisado para comunicar o cliente. */
    public function test_rn37_contato_sem_email(): void
    {
        $this->criarDadosDeColeta();
        $this->cliente->contatoPrincipal->update(['email' => null]);

        $this->post("/orcamentos/{$this->orcamento->id}/coletas", $this->dadosAgendamento([$this->reservatorio]))
            ->assertSessionHas('erro', 'O contato principal do cliente não tem e-mail: comunique o cliente por outro meio.');
        $this->assertSame(1, Coleta::count());
        Mail::assertNothingSent();
    }

    /** Matriz 7.4: Coletor e Analista não agendam coletas. */
    public function test_somente_recepcao_e_administrador_agendam(): void
    {
        $this->criarDadosDeColeta();
        $this->actingAs($this->coletor);

        $this->get("/orcamentos/{$this->orcamento->id}/coletas/create")->assertForbidden();
        $this->post("/orcamentos/{$this->orcamento->id}/coletas", $this->dadosAgendamento([$this->reservatorio]))->assertForbidden();
    }
}
