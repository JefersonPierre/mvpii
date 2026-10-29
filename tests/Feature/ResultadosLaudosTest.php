<?php

namespace Tests\Feature;

use App\Mail\LaudoMail;
use App\Models\Amostra;
use App\Models\Laudo;
use App\Models\Legislacao;
use App\Models\Resultado;
use App\Models\Usuario;
use App\Support\Conformidade;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\DadosDeResultados;
use Tests\TestCase;

// MOD12 e MOD13 – UC24 a UC28: resultados, revisão, laudo, retificação e verificação (RF48–RF57, RN50–RN57)
class ResultadosLaudosTest extends TestCase
{
    use DadosDeResultados, RefreshDatabase;

    private Amostra $amostra;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->criarCatalogoComLimites();
        $this->amostra = $this->amostrasRecebidas()->amostras->first();
        $this->admin = Usuario::factory()->comPerfis(Perfis::ADMINISTRADOR)->create();
    }

    private function lancar(array $alterar = [], string $acao = 'enviar', ?Usuario $quem = null)
    {
        return $this->actingAs($quem ?? $this->analista)->put(route('resultados.update', $this->amostra),
            ['resultados' => $this->resultadosConformes($alterar), 'acao' => $acao]);
    }

    /** Lança, libera e emite o laudo pela tela, como Analista e Administrador. */
    private function emitirLaudo(): Laudo
    {
        $this->lancar()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('resultados.liberar', $this->amostra))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('laudos.store'), ['amostras' => [$this->amostra->id], 'acao' => 'enviar'])->assertSessionHasNoErrors();

        return Laudo::where('amostra_id', $this->amostra->id)->sole();
    }

    public function test_ct33_conformidade_calculada_no_lancamento(): void
    {
        $this->lancar(['ph' => ['valor' => '9,7'], 'coliformes' => ['presenca' => 'PRESENTE']], 'salvar')->assertSessionHasNoErrors();

        $r = Resultado::where('amostra_id', $this->amostra->id)->get()->keyBy('parametro_id');
        $this->assertSame(Conformidade::NAO_CONFORME, $r[$this->parametros['ph']->id]->conformidade);
        $this->assertSame(Conformidade::NAO_CONFORME, $r[$this->parametros['coliformes']->id]->conformidade);
        $this->assertSame(Conformidade::CONFORME, $r[$this->parametros['turbidez']->id]->conformidade);
        $this->assertSame(Amostra::EM_ANALISE, $this->amostra->fresh()->situacao);
        $this->assertSame('NAO_CONFORME', $this->amostra->fresh()->conformidade);
        // RN52: o limite fica copiado no resultado.
        $this->assertSame('FAIXA', $r[$this->parametros['ph']->id]->limite_tipo);
    }

    public function test_ct34_menor_que_lq_e_parametro_sem_limite(): void
    {
        $this->lancar(['turbidez' => ['menor_que_lq' => '1']], 'salvar')->assertSessionHasNoErrors();

        $r = Resultado::where('amostra_id', $this->amostra->id)->get()->keyBy('parametro_id');
        $this->assertSame(Conformidade::CONFORME, $r[$this->parametros['turbidez']->id]->conformidade);
        $this->assertSame(Conformidade::SEM_LIMITE, $r[$this->parametros['cor']->id]->conformidade);
    }

    public function test_rn46_medicao_de_campo_entra_como_resultado(): void
    {
        $ph = Resultado::where(['amostra_id' => $this->amostra->id, 'parametro_id' => $this->parametros['ph']->id])->sole();

        $this->assertSame(Resultado::CAMPO, $ph->origem);
        $this->assertSame($this->coletor->id, $ph->lancado_por_id);
    }

    public function test_rn50_envio_para_revisao_exige_todos_os_parametros(): void
    {
        $this->lancar(['cor' => ['valor' => '']])->assertSessionHasErrors('resultados');

        $this->assertSame(Amostra::RECEBIDA, $this->amostra->fresh()->situacao);
    }

    public function test_ct35_usa_a_legislacao_vigente_na_data_da_coleta(): void
    {
        // A Portaria 888 foi substituída por uma nova a partir de amanhã; a coleta foi hoje.
        $this->portaria888->update(['fim_vigencia' => today()->toDateString()]);
        $nova = Legislacao::factory()->create(['nome' => 'Portaria nova', 'inicio_vigencia' => today()->addDay()->toDateString()]);
        $nova->tiposAmostra()->sync([$this->agua->id]);
        $antiga = Legislacao::factory()->create(['nome' => 'Portaria revogada', 'inicio_vigencia' => '2010-01-01', 'fim_vigencia' => today()->subDay()->toDateString()]);
        $antiga->tiposAmostra()->sync([$this->agua->id]);

        $this->lancar()->assertSessionHasNoErrors();

        $this->assertSame($this->portaria888->id, $this->amostra->fresh()->legislacao_id);
    }

    public function test_ct36_quem_lancou_nao_libera(): void
    {
        $this->lancar()->assertSessionHasNoErrors();

        $this->actingAs($this->analista)->get(route('resultados.revisao', $this->amostra))->assertOk()->assertSee('por outro usuário');
        $this->actingAs($this->analista)->post(route('resultados.liberar', $this->amostra))->assertSessionHasErrors();
        $this->assertSame(Amostra::EM_REVISAO, $this->amostra->fresh()->situacao);

        // A revisão não lista a amostra para quem lançou (UC25 1a).
        $this->actingAs($this->analista)->get(route('resultados.index', ['aba' => 'revisar']))->assertDontSee($this->amostra->codigo);
    }

    public function test_rn53_o_coletor_da_medicao_de_campo_tambem_nao_libera(): void
    {
        $this->coletor->definirPerfis([Perfis::COLETOR, Perfis::ANALISTA]);
        $this->lancar()->assertSessionHasNoErrors();

        $this->actingAs($this->coletor->fresh())->post(route('resultados.liberar', $this->amostra))->assertSessionHasErrors();
    }

    public function test_uc25_devolver_exige_motivo_e_volta_para_em_analise(): void
    {
        $this->lancar()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('resultados.devolver', $this->amostra), ['motivo_devolucao' => ''])->assertSessionHasErrors('motivo_devolucao');
        $this->actingAs($this->admin)->post(route('resultados.devolver', $this->amostra), ['motivo_devolucao' => 'Conferir a turbidez'])->assertSessionHasNoErrors();

        $this->assertSame(Amostra::EM_ANALISE, $this->amostra->fresh()->situacao);
        $this->assertSame('Conferir a turbidez', $this->amostra->fresh()->motivo_devolucao);
    }

    public function test_ct37_laudo_de_amostra_nao_liberada_e_recusado(): void
    {
        $this->lancar()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('laudos.store'), ['amostras' => [$this->amostra->id]])->assertSessionHasErrors('amostras');

        $this->assertSame(0, Laudo::count());
    }

    public function test_rn54_so_o_administrador_emite_laudo(): void
    {
        $this->actingAs($this->analista)->get(route('laudos.emitir'))->assertForbidden();
    }

    public function test_ct38_emissao_numera_guarda_o_pdf_com_hash_e_envia_ao_contato_principal(): void
    {
        $laudo = $this->emitirLaudo();

        $this->assertSame(sprintf('LAU-%d-0001', now()->year), $laudo->numero());
        $this->assertSame(1, $laudo->revisao);
        $this->assertSame(Laudo::VIGENTE, $laudo->situacao);
        $this->assertSame('CONFORME', $laudo->conclusao);
        Storage::disk('local')->assertExists($laudo->pdf_caminho);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($laudo->pdf_caminho)), $laudo->pdf_hash);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($laudo->pdf_caminho));

        $email = $this->cliente->contatoPrincipal->email;
        $this->assertSame($email, $laudo->envio_email);
        Mail::assertSent(LaudoMail::class, fn (LaudoMail $m) => $m->hasTo($email));
        $this->assertSame(Amostra::LAUDO_EMITIDO, $this->amostra->fresh()->situacao);

        // RNF17: o PDF baixado é o guardado na emissão.
        $this->actingAs($this->admin)->get(route('laudos.pdf', $laudo))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_ct39_retificacao_gera_revisao_2_e_substitui_a_anterior(): void
    {
        $laudo = $this->emitirLaudo();

        $this->actingAs($this->admin)->post(route('laudos.retificar', $laudo), ['motivo_retificacao' => ''])->assertSessionHasErrors('motivo_retificacao');
        $this->assertSame(Amostra::LAUDO_EMITIDO, $this->amostra->fresh()->situacao);

        $this->actingAs($this->admin)->post(route('laudos.retificar', $laudo), ['motivo_retificacao' => 'Turbidez digitada errada', 'reabrir_resultados' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Amostra::EM_ANALISE, $this->amostra->fresh()->situacao);
        // Outro analista corrige; o administrador, que não lançou, libera.
        $outro = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create();
        $this->lancar(['turbidez' => ['valor' => '1,2']], quem: $outro)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('resultados.liberar', $this->amostra))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('laudos.store'), ['amostras' => [$this->amostra->id]])->assertSessionHasNoErrors();

        $nova = Laudo::where('amostra_id', $this->amostra->id)->where('revisao', 2)->sole();
        $this->assertSame(Laudo::VIGENTE, $nova->situacao);
        $this->assertSame($laudo->numero(), $nova->numero());
        $this->assertSame($laudo->id, $nova->substitui_id);
        $this->assertSame('Turbidez digitada errada', $nova->motivo_retificacao);
        $this->assertSame(Laudo::SUBSTITUIDO, $laudo->fresh()->situacao);
    }

    public function test_ct40_verificacao_publica(): void
    {
        $laudo = $this->emitirLaudo();
        $this->actingAs($this->admin)->post(route('laudos.retificar', $laudo), ['motivo_retificacao' => 'Dados do ponto', 'reabrir_resultados' => '0']);
        $this->actingAs($this->admin)->post(route('laudos.store'), ['amostras' => [$this->amostra->id]])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('laudos.verificar', $laudo->codigo_verificacao))->assertOk()
            ->assertSee('Substituído')->assertDontSee('Turbidez')->assertDontSee('0,8');
        $vigente = Laudo::where('situacao', Laudo::VIGENTE)->sole();
        $this->get(route('laudos.verificar', ['codigo' => strtolower($vigente->codigo_verificacao)]))->assertOk()->assertSee('autêntico e vigente');
        $this->get(route('laudos.verificar', 'ZZZZZZZZZZ'))->assertOk()->assertSee('Laudo não encontrado');
    }

    public function test_uc28_consulta_de_nao_conformes(): void
    {
        $this->lancar(['ph' => ['valor' => '9,7']])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('laudos.index', ['aba' => 'nao-conformes']))->assertOk()
            ->assertSee($this->amostra->codigo)->assertSee('pH');
        $this->actingAs($this->coletor)->get(route('laudos.index'))->assertForbidden();
    }
}
