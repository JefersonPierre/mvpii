<?php

namespace Tests\Feature;

use App\Mail\OrcamentoMail;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

// UC09 – Enviar orçamento e UC10 – Registrar resposta (RF18, RF19, RN17, RN18, RN19)
class EnvioRespostaOrcamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(Usuario::factory()->create());
    }

    private function rascunho(?Cliente $cliente = null): Orcamento
    {
        $cliente ??= Cliente::factory()->create();
        $agua = TipoAmostra::factory()->create();
        $pacote = Pacote::factory()->create(['tipo_amostra_id' => $agua->id, 'preco' => '150.00']);

        $this->post(route('orcamentos.store'), [
            'cliente_id' => $cliente->id, 'contato_id' => $cliente->contatoPrincipal->id, 'tipo_amostra_id' => $agua->id,
            'itens' => [['referencia' => 'PACOTE:'.$pacote->id, 'quantidade' => 2]],
            'taxa_coleta' => '50,00', 'desconto_percentual' => '0', 'validade_dias' => 30,
        ])->assertSessionHasNoErrors();

        return Orcamento::latest('id')->first();
    }

    private function enviado(?Cliente $cliente = null): Orcamento
    {
        $orcamento = $this->rascunho($cliente);
        Mail::fake();
        $this->post(route('orcamentos.envio', $orcamento), ['forma' => 'email', 'email' => 'contato@cliente.com', 'mensagem' => 'Segue.']);

        return $orcamento->fresh();
    }

    public function test_previa_do_pdf(): void
    {
        $orcamento = $this->rascunho();

        $this->get(route('orcamentos.pdf', $orcamento))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('orcamentos.envio', $orcamento))->assertOk()->assertSee($orcamento->cliente->contatoPrincipal->email);
    }

    public function test_ct10_envia_por_email_com_pdf_e_muda_para_enviado(): void
    {
        $orcamento = $this->rascunho();
        Mail::fake();

        $this->post(route('orcamentos.envio', $orcamento), ['forma' => 'email', 'email' => 'contato@cliente.com', 'mensagem' => 'Segue o orçamento.'])
            ->assertRedirect(route('orcamentos.show', $orcamento))
            ->assertSessionHas('sucesso', 'Orçamento enviado para contato@cliente.com.');

        Mail::assertSent(OrcamentoMail::class, fn (OrcamentoMail $m) => $m->hasTo('contato@cliente.com')
            && count($m->attachments()) === 1 && $m->mensagem === 'Segue o orçamento.');

        $orcamento->refresh();
        $this->assertSame(Orcamento::ENVIADO, $orcamento->situacao);
        $this->assertSame(today()->addDays(30)->toDateString(), $orcamento->valido_ate->toDateString());
        Storage::disk('local')->assertExists($orcamento->pdf_caminho);
        $this->get(route('orcamentos.edit', $orcamento))->assertRedirect(route('orcamentos.show', $orcamento));

        // RN08: o envio fica no histórico, com a validade calculada.
        $historico = RegistroAuditoria::where(['entidade' => 'ORCAMENTO', 'registro_id' => $orcamento->id, 'acao' => 'ALTERACAO'])
            ->pluck('valor_novo', 'campo');
        $this->assertSame('Enviado', $historico['situacao']);
        $this->assertSame(today()->addDays(30)->format('d/m/Y'), $historico['valido_ate']);
        $this->assertSame('contato@cliente.com', $historico['envio_email']);
    }

    public function test_uc09_2a_envio_manual_sem_email(): void
    {
        $orcamento = $this->rascunho();
        Mail::fake();

        $this->post(route('orcamentos.envio', $orcamento), ['forma' => 'manual'])->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $orcamento->refresh();
        $this->assertSame(Orcamento::ENVIADO, $orcamento->situacao);
        $this->assertNull($orcamento->envio_email);
        $this->get(route('orcamentos.show', $orcamento))->assertSee('(envio manual)');
    }

    public function test_uc09_3a_falha_no_email_mantem_rascunho(): void
    {
        $orcamento = $this->rascunho();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Servidor SMTP fora do ar'));

        $this->post(route('orcamentos.envio', $orcamento), ['forma' => 'email', 'email' => 'contato@cliente.com'])
            ->assertSessionHas('erro', 'Não foi possível enviar o e-mail agora. O orçamento continua em Rascunho; tente de novo em instantes.');

        $this->assertSame(Orcamento::RASCUNHO, $orcamento->fresh()->situacao);
        $this->assertNull($orcamento->fresh()->valido_ate);
    }

    public function test_tela_inicial_mostra_orcamentos_aguardando_resposta_e_rascunhos(): void
    {
        $enviado = $this->enviado();
        $rascunho = $this->rascunho();

        $this->get(route('inicio'))->assertOk()
            ->assertSee('Aguardando resposta do cliente (1)')->assertSee($enviado->numeroComRevisao())->assertSee('vence em 30 dias')
            ->assertSee('Rascunhos (1)')->assertSee($rascunho->numeroComRevisao());
    }

    public function test_aprova_orcamento_de_cliente_completo(): void
    {
        $orcamento = $this->enviado();

        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'APROVADO', 'data_resposta' => today()->toDateString()])
            ->assertSessionHas('sucesso');

        $this->assertSame(Orcamento::APROVADO, $orcamento->fresh()->situacao);
        $this->get(route('orcamentos.show', $orcamento))->assertSee('Pronto para o agendamento da coleta');
    }

    public function test_ct11_rn18_interessado_precisa_completar_o_cadastro_para_aprovar(): void
    {
        $orcamento = $this->enviado(Cliente::factory()->interessado()->create());

        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'APROVADO', 'data_resposta' => today()->toDateString()])
            ->assertSessionHas('erro', 'Complete o cadastro do cliente (CPF/CNPJ e endereço) antes de aprovar o orçamento.');

        $this->assertSame(Orcamento::ENVIADO, $orcamento->fresh()->situacao);
        $this->get(route('orcamentos.show', $orcamento))->assertSee('Completar cadastro');
    }

    public function test_completar_cadastro_volta_para_o_orcamento(): void
    {
        $interessado = Cliente::factory()->interessado()->create();
        $orcamento = $this->enviado($interessado);
        $contato = $interessado->contatoPrincipal;

        $this->put(route('clientes.update', $interessado), [
            'cadastro' => 'cliente', 'tipo_pessoa' => 'J', 'nome' => $interessado->nome, 'documento' => '11.222.333/0001-81',
            'cep' => '13010-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Campinas', 'uf' => 'SP',
            'contatos' => ["c{$contato->id}" => ['id' => $contato->id, 'nome' => $contato->nome, 'telefone' => $contato->telefone]],
            'principal' => "c{$contato->id}", 'voltar_orcamento' => $orcamento->id,
        ])->assertRedirect(route('orcamentos.show', $orcamento));

        $this->assertTrue($interessado->fresh()->cadastroCompleto());
        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'APROVADO', 'data_resposta' => today()->toDateString()]);
        $this->assertSame(Orcamento::APROVADO, $orcamento->fresh()->situacao);
    }

    public function test_ct12_rn19_recusa_exige_motivo(): void
    {
        $orcamento = $this->enviado();

        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'RECUSADO', 'data_resposta' => today()->toDateString()])
            ->assertSessionHasErrors(['motivo_recusa' => 'Informe o motivo: preço, prazo, desistência ou outro.']);
        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'RECUSADO', 'data_resposta' => today()->toDateString(), 'motivo_recusa' => 'OUTRO'])
            ->assertSessionHasErrors(['motivo_detalhe' => 'Descreva o motivo da recusa.']);
        $this->assertSame(Orcamento::ENVIADO, $orcamento->fresh()->situacao);

        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'RECUSADO', 'data_resposta' => today()->toDateString(), 'motivo_recusa' => 'PRECO']);
        $orcamento->refresh();
        $this->assertSame([Orcamento::RECUSADO, 'PRECO'], [$orcamento->situacao, $orcamento->motivo_recusa]);
        $this->get(route('orcamentos.show', $orcamento))->assertSee('Motivo: Preço');
    }

    public function test_data_da_resposta_nao_pode_ser_futura(): void
    {
        $orcamento = $this->enviado();

        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'APROVADO', 'data_resposta' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors(['data_resposta' => 'A data da resposta não pode ser futura.']);
    }

    public function test_rn17_vencido_nao_recebe_resposta(): void
    {
        $orcamento = $this->enviado();
        $this->travel(31)->days();

        $this->post(route('orcamentos.resposta', $orcamento), ['resultado' => 'APROVADO', 'data_resposta' => today()->toDateString()])
            ->assertSessionHas('erro', 'A validade deste orçamento venceu. Para retomar, crie uma nova revisão.');
        $this->assertSame(Orcamento::EXPIRADO, $orcamento->fresh()->situacao);

        // UC10 1b: reaberto só como nova revisão.
        $this->post(route('orcamentos.revisao', $orcamento))->assertRedirect();
        $this->assertSame(2, Orcamento::where('sequencia', $orcamento->sequencia)->max('revisao'));
    }
}
