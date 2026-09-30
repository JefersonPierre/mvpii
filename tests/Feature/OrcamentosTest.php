<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Configuracao;
use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC08 – Elaborar orçamento, UC11 – Consultar (RF17, RF20, RF21, RN12–RN17)
class OrcamentosTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private TipoAmostra $agua;

    private Pacote $potabilidade;

    private Parametro $ferro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Usuario::factory()->create(['nome' => 'Recepção']));
        $this->cliente = Cliente::factory()->create(['nome' => 'Residencial Primavera']);
        $this->agua = TipoAmostra::factory()->create(['nome' => 'Água potável']);
        $this->potabilidade = Pacote::factory()->create(['nome' => 'Potabilidade básica', 'tipo_amostra_id' => $this->agua->id, 'preco' => '150.00']);
        $this->potabilidade->parametros()->attach(Parametro::factory()->create(['nome' => 'pH']));
        $this->ferro = Parametro::factory()->create(['nome' => 'Ferro total', 'preco' => '40.00']);
    }

    /** CT08: pacote R$ 150 × 2, parâmetro R$ 40 × 2, taxa R$ 50 e desconto de 10%. */
    private function dadosCt08(array $alterar = []): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'contato_id' => $this->cliente->contatoPrincipal->id,
            'tipo_amostra_id' => $this->agua->id,
            'itens' => [
                ['referencia' => 'PACOTE:'.$this->potabilidade->id, 'quantidade' => 2],
                ['referencia' => 'PARAMETRO:'.$this->ferro->id, 'quantidade' => 2],
            ],
            'taxa_coleta' => '50,00',
            'desconto_percentual' => '10',
            'validade_dias' => 30,
            'condicoes_pagamento' => 'À vista.',
            ...$alterar,
        ];
    }

    private function criarOrcamento(array $alterar = []): Orcamento
    {
        $this->post(route('orcamentos.store'), $this->dadosCt08($alterar))->assertSessionHasNoErrors();

        return Orcamento::latest('id')->first();
    }

    public function test_ct08_calcula_o_total_e_grava_como_rascunho(): void
    {
        $orcamento = $this->criarOrcamento();

        $this->assertSame(Orcamento::RASCUNHO, $orcamento->situacao);
        $this->assertSame(['380.00', '43.00', '387.00'], [$orcamento->subtotal_itens, $orcamento->valor_desconto, $orcamento->valor_total]);
        $this->assertSame(['300.00', '80.00'], $orcamento->itens->pluck('subtotal')->all());
        $this->assertSame('pH', $orcamento->itens[0]->parametros_incluidos);

        $this->get(route('orcamentos.show', $orcamento))->assertOk()
            ->assertSee($orcamento->numero())->assertSee('R$ 387,00')->assertSee('Rascunho');
    }

    public function test_rn12_numero_sequencial_por_ano(): void
    {
        $ano = now()->format('Y');
        $primeiro = $this->criarOrcamento();
        $segundo = $this->criarOrcamento();

        $this->assertSame("ORC-{$ano}-0001", $primeiro->numero());
        $this->assertSame("ORC-{$ano}-0002", $segundo->numero());

        $this->travelTo(now()->addYear()->startOfYear());
        $this->assertSame('ORC-'.($ano + 1).'-0001', $this->criarOrcamento()->numero());
    }

    public function test_ct09_rn14_desconto_acima_do_maximo_nao_grava(): void
    {
        Configuracao::atual()->update(['desconto_maximo' => 15]);

        $this->post(route('orcamentos.store'), $this->dadosCt08(['desconto_percentual' => '25']))
            ->assertSessionHasErrors(['desconto_percentual' => 'Máximo permitido: 15%.']);
        $this->assertSame(0, Orcamento::count());
    }

    public function test_rn15_mudanca_no_catalogo_nao_altera_o_orcamento(): void
    {
        $orcamento = $this->criarOrcamento();
        $this->potabilidade->update(['preco' => '999.00']);

        // Salvar o rascunho de novo mantém o preço copiado dos itens que já estavam nele.
        $itens = $orcamento->itens->map(fn ($i) => ['id' => $i->id, 'referencia' => $i->referencia(), 'quantidade' => $i->quantidade])->all();
        $this->put(route('orcamentos.update', $orcamento), $this->dadosCt08(['itens' => $itens]))->assertSessionHasNoErrors();

        $this->assertSame('387.00', $orcamento->fresh()->valor_total);
    }

    public function test_valida_itens_do_orcamento(): void
    {
        $efluente = TipoAmostra::factory()->create();
        $pacoteEfluente = Pacote::factory()->create(['tipo_amostra_id' => $efluente->id]);
        $inativo = Parametro::factory()->inativo()->create();

        $this->post(route('orcamentos.store'), $this->dadosCt08(['itens' => []]))
            ->assertSessionHasErrors(['itens' => 'Adicione ao menos um pacote ou parâmetro.']);
        $this->post(route('orcamentos.store'), $this->dadosCt08(['itens' => [
            ['referencia' => 'PACOTE:'.$pacoteEfluente->id, 'quantidade' => 1],
            ['referencia' => 'PARAMETRO:'.$inativo->id, 'quantidade' => 1],
            ['referencia' => 'PARAMETRO:'.$this->ferro->id, 'quantidade' => 1],
            ['referencia' => 'PARAMETRO:'.$this->ferro->id, 'quantidade' => 2],
        ]]))->assertSessionHasErrors([
            'itens.0.referencia' => "O pacote {$pacoteEfluente->nome} é de outro tipo de amostra.",
            'itens.1.referencia' => 'Item inativo ou inexistente no catálogo.',
            'itens.3.referencia' => 'Item repetido: ajuste a quantidade na linha já existente.',
        ]);
    }

    public function test_cliente_inativo_nao_recebe_orcamento(): void
    {
        $this->cliente->update(['ativo' => false]);

        $this->post(route('orcamentos.store'), $this->dadosCt08())
            ->assertSessionHasErrors(['cliente_id' => 'Cliente inativo não pode receber orçamento.']);
    }

    public function test_rn16_orcamento_enviado_nao_pode_ser_editado(): void
    {
        $orcamento = $this->criarOrcamento();
        $orcamento->forceFill(['situacao' => Orcamento::ENVIADO])->save();

        $this->get(route('orcamentos.edit', $orcamento))->assertRedirect(route('orcamentos.show', $orcamento));
        $this->put(route('orcamentos.update', $orcamento), $this->dadosCt08(['desconto_percentual' => '0']))->assertSessionHas('erro');
        $this->assertSame('387.00', $orcamento->fresh()->valor_total);
    }

    public function test_rf20_nova_revisao_mantem_numero_e_substitui_a_anterior(): void
    {
        $r1 = $this->criarOrcamento();
        $r1->forceFill(['situacao' => Orcamento::ENVIADO, 'data_envio' => now(), 'valido_ate' => now()->addDays(30)])->save();
        $this->potabilidade->update(['preco' => '200.00']);

        $this->post(route('orcamentos.revisao', $r1))->assertRedirect();

        $r2 = Orcamento::where('revisao', 2)->sole();
        $this->assertSame([$r1->ano, $r1->sequencia, Orcamento::RASCUNHO, $r1->id], [$r2->ano, $r2->sequencia, $r2->situacao, $r2->substitui_id]);
        $this->assertSame('387.00', $r2->valor_total, 'A revisão mantém os preços copiados.');
        $this->assertSame(Orcamento::SUBSTITUIDO, $r1->fresh()->situacao);
        $this->get(route('orcamentos.show', $r2))->assertSee('r1')->assertSee('Substituído');
    }

    public function test_nova_revisao_de_rascunho_nao_e_permitida(): void
    {
        $rascunho = $this->criarOrcamento();

        $this->post(route('orcamentos.revisao', $rascunho))->assertSessionHas('erro');
        $this->assertSame(1, Orcamento::count());
    }

    public function test_rf20_duplicar_cria_novo_numero_com_precos_atuais(): void
    {
        $origem = $this->criarOrcamento();
        $this->potabilidade->update(['preco' => '200.00']);
        $this->ferro->update(['ativo' => false]);

        $this->post(route('orcamentos.duplicar', $origem))->assertSessionHas('erro', 'Itens inativos no catálogo ficaram de fora: Ferro total.');

        $copia = Orcamento::latest('id')->first();
        $this->assertNotSame($origem->sequencia, $copia->sequencia);
        $this->assertSame(Orcamento::RASCUNHO, $copia->situacao);
        $this->assertSame(['Pacote Potabilidade básica'], $copia->itens->pluck('descricao')->all());
        $this->assertSame('200.00', $copia->itens[0]->preco_unitario);
    }

    public function test_rf21_pesquisa_por_numero_cliente_e_situacao(): void
    {
        $primeiro = $this->criarOrcamento();
        $outroCliente = Cliente::factory()->create(['nome' => 'Indústria Beta']);
        $this->criarOrcamento(['cliente_id' => $outroCliente->id, 'contato_id' => null]);

        $this->get(route('orcamentos.index', ['numero' => $primeiro->numero()]))->assertSee('Residencial Primavera')->assertDontSee('Indústria Beta');
        $this->get(route('orcamentos.index', ['numero' => '2']))->assertSee('Indústria Beta')->assertDontSee('Residencial Primavera');
        $this->get(route('orcamentos.index', ['cliente' => 'beta']))->assertSee('Indústria Beta')->assertDontSee('Residencial Primavera');
        $this->get(route('orcamentos.index', ['situacao' => 'APROVADO']))->assertSee('Nenhum orçamento encontrado.');
    }

    public function test_rn17_enviado_vencido_expira_automaticamente_pelo_sistema(): void
    {
        $vencido = Orcamento::factory()->enviado(now()->subDay()->toDateString())->create();
        $emDia = Orcamento::factory()->enviado(now()->toDateString())->create();

        $this->artisan('orcamentos:expirar')->expectsOutput('1 orçamento(s) expirado(s).')->assertSuccessful();

        $this->assertSame(Orcamento::EXPIRADO, $vencido->fresh()->situacao);
        $this->assertSame(Orcamento::ENVIADO, $emDia->fresh()->situacao);
        $registro = RegistroAuditoria::where(['entidade' => 'ORCAMENTO', 'registro_id' => $vencido->id, 'campo' => 'situacao'])->sole();
        $this->assertNull($registro->usuario_id, 'A expiração é registrada como feita pelo sistema.');
        $this->get(route('orcamentos.show', $vencido))->assertSee('Sistema');
    }

    public function test_cadastro_rapido_de_interessado_volta_para_o_orcamento(): void
    {
        $this->post(route('clientes.store'), [
            'cadastro' => 'interessado', 'tipo_pessoa' => 'F', 'nome' => 'João Interessado',
            'contatos' => ['n1' => ['nome' => 'João', 'email' => 'joao@exemplo.com']], 'principal' => 'n1',
            'voltar_orcamento' => 'novo',
        ])->assertRedirect(route('orcamentos.create', ['cliente' => Cliente::where('nome', 'João Interessado')->value('id')]));
    }
}
