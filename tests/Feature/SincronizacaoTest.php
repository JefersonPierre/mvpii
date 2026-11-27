<?php

namespace Tests\Feature;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Dispositivo;
use App\Models\RegistroAuditoria;
use App\Models\Resultado;
use App\Models\Sincronizacao;
use App\Models\Usuario;
use App\Services\AcessoDispositivos;
use App\Services\AgendamentoColetas;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\DadosDeResultados;
use Tests\TestCase;

// MOD10 – UC21, UC22 e UC23: registro pelo celular, sincronização e conflitos (RF39–RF42, RN38–RN43)
class SincronizacaoTest extends TestCase
{
    use DadosDeResultados, RefreshDatabase;

    private Coleta $coleta;

    private Usuario $recepcao;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->criarCatalogoComLimites(6);
        $this->recepcao = Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create();
        Auth::setUser($this->recepcao);
        $this->coleta = $this->agendar([$this->reservatorio, $this->portaria], ['data' => today()->toDateString()]);
        Auth::logout();
    }

    private function token(?Usuario $usuario = null): string
    {
        return app(AcessoDispositivos::class)->conectar($usuario ?? $this->coletor, 'Chrome no Android')['token'];
    }

    /** Registro como o aplicativo envia (UC21): 2 amostras coletadas, pH medido em campo. */
    private function registro(array $alterar = []): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'coleta_id' => $this->coleta->id,
            'agendada_para' => today()->toDateString(),
            'acao' => 'REALIZADA',
            'origem' => 'CELULAR_SEM_CONEXAO',
            'data_hora_coleta' => now()->subMinutes(40)->toIso8601String(),
            'condicoes_tempo' => 'Nublado',
            'latitude' => -22.9056,
            'longitude' => -47.0608,
            'amostras' => $this->coleta->amostras->map(fn (Amostra $a) => [
                'id' => $a->id, 'situacao' => 'COLETADA', 'temperatura' => '6,5',
                'medicoes' => [$this->parametros['ph']->id => '7,4', $this->parametros['cloro']->id => '1,2'],
            ])->all(),
            ...$alterar,
        ];
    }

    private function enviar(array $registro, ?string $token = null)
    {
        return $this->withToken($token ?? $this->token())->postJson('/api/sincronizacao/coletas', $registro);
    }

    /** CT26: registro pendente enviado → coleta Realizada no servidor, com a hora da coleta e as medições de campo. */
    public function test_ct26_envio_do_registro_pendente_realiza_a_coleta(): void
    {
        $registro = $this->registro();
        $this->enviar($registro)->assertOk()->assertJson(['situacao' => 'APLICADA', 'coleta' => ['situacao' => 'REALIZADA']]);

        $this->coleta->refresh();
        $this->assertSame([Coleta::REALIZADA, 'CELULAR_SEM_CONEXAO', $this->coletor->id], [$this->coleta->situacao, $this->coleta->origem_registro, $this->coleta->registrada_por_id]);
        // RN41: vale a hora informada no aparelho, não a do envio.
        $this->assertSame(now()->subMinutes(40)->format('Y-m-d H:i'), $this->coleta->data_hora_coleta->format('Y-m-d H:i'));
        $this->assertNotNull($this->coleta->registro_recebido_em);
        // RN46: medições de campo viram resultados com origem Campo.
        $resultado = Resultado::where(['amostra_id' => $this->coleta->amostras[0]->id, 'parametro_id' => $this->parametros['ph']->id])->first();
        $this->assertSame([Resultado::CAMPO, 7.4, $this->coletor->id], [$resultado->origem, (float) $resultado->valor, $resultado->lancado_por_id]);
        $this->assertSame([Amostra::COLETADA, Amostra::COLETADA], $this->coleta->amostras()->pluck('situacao')->all());
    }

    /** CT27: o mesmo registro enviado duas vezes → uma única coleta registrada e o mesmo resultado. */
    public function test_ct27_reenvio_do_mesmo_identificador_nao_duplica(): void
    {
        $registro = $this->registro();
        $token = $this->token();
        $primeira = $this->enviar($registro, $token)->assertOk()->json();
        $segunda = $this->enviar($registro, $token)->assertOk()->json();

        $this->assertSame($primeira, $segunda);
        $this->assertSame(1, Sincronizacao::where('uuid', $registro['uuid'])->count());
        $this->assertSame(1, RegistroAuditoria::where(['entidade' => 'COLETA', 'registro_id' => $this->coleta->id, 'campo' => 'situacao', 'valor_novo' => 'Realizada'])->count());
    }

    /** CT28: coleta cancelada enquanto o coletor estava sem conexão → continua Cancelada e o conflito fica pendente. */
    public function test_ct28_coleta_cancelada_vira_conflito(): void
    {
        Auth::setUser($this->recepcao);
        app(AgendamentoColetas::class)->cancelar($this->coleta, 'Cliente pediu para remarcar');
        Auth::logout();

        $this->enviar($this->registro())->assertOk()->assertJson(['situacao' => 'CONFLITO']);

        $this->assertSame(Coleta::CANCELADA, $this->coleta->fresh()->situacao);
        $conflito = Sincronizacao::firstOrFail();
        $this->assertSame([Sincronizacao::CONFLITO, 'a coleta foi cancelada.'], [$conflito->situacao, $conflito->motivo_conflito]);

        // A Recepção é avisada na tela inicial.
        $this->actingAs($this->recepcao)->get(route('inicio'))->assertSee('Conflitos de sincronização');
    }

    /** CT29: aceitar o conflito → coleta Realizada com os dados do celular e decisão na auditoria; descartar exige motivo. */
    public function test_ct29_aceitar_ou_descartar_conflito(): void
    {
        Auth::setUser($this->recepcao);
        app(AgendamentoColetas::class)->cancelar($this->coleta, 'Cliente pediu para remarcar');
        Auth::logout();
        $this->enviar($this->registro())->assertJson(['situacao' => 'CONFLITO']);
        $conflito = Sincronizacao::firstOrFail();

        $this->actingAs($this->recepcao);
        $this->get(route('conflitos.show', $conflito))->assertOk()->assertSee('a coleta foi cancelada.')->assertSee('Nublado');
        $this->post(route('conflitos.descartar', $conflito), ['motivo_descarte' => ''])->assertSessionHasErrors('motivo_descarte');
        $this->assertSame(Sincronizacao::CONFLITO, $conflito->fresh()->situacao);

        $this->post(route('conflitos.aceitar', $conflito))->assertRedirect()->assertSessionHasNoErrors();
        $this->coleta->refresh();
        $this->assertSame([Coleta::REALIZADA, $this->coletor->id, 'CELULAR_SEM_CONEXAO'], [$this->coleta->situacao, $this->coleta->registrada_por_id, $this->coleta->origem_registro]);
        $this->assertSame([Amostra::COLETADA, Amostra::COLETADA], $this->coleta->amostras()->pluck('situacao')->all());
        $this->assertSame([Sincronizacao::ACEITA, $this->recepcao->id], [$conflito->fresh()->situacao, $conflito->fresh()->resolvida_por_id]);
        $this->assertDatabaseHas('registros_auditoria', ['entidade' => 'SINCRONIZACAO', 'registro_id' => $conflito->id,
            'campo' => 'situacao', 'valor_novo' => 'Aceita', 'usuario_id' => $this->recepcao->id]);
    }

    /** CT30: o aparelho do coletor A guarda só as coletas Agendadas dele para hoje e amanhã, sem valores do orçamento. */
    public function test_ct30_aparelho_guarda_so_as_coletas_do_coletor(): void
    {
        Auth::setUser($this->recepcao);
        $amanha = $this->agendar([$this->reservatorio], ['data' => today()->addDay()->toDateString(), 'periodo' => 'TARDE']);
        $depois = $this->agendar([$this->reservatorio], ['data' => today()->addDays(2)->toDateString()]);
        $outro = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();
        $deOutro = $this->agendar([$this->portaria], ['data' => today()->toDateString(), 'coletor_id' => $outro->id, 'periodo' => 'TARDE']);
        Auth::logout();

        $resposta = $this->withToken($this->token())->getJson('/api/campo/coletas')->assertOk();
        $ids = collect($resposta->json('dias'))->flatMap(fn ($d) => array_column($d['coletas'], 'id'))->all();

        $this->assertEqualsCanonicalizing([$this->coleta->id, $amanha->id], $ids);
        $this->assertNotContains($depois->id, $ids);
        $this->assertNotContains($deOutro->id, $ids);
        $json = $resposta->getContent();
        foreach (['preco', 'valor_total', 'subtotal', 'taxa_coleta'] as $campo) {
            $this->assertStringNotContainsString($campo, $json);
        }
        $this->assertSame('pH', $resposta->json('dias.0.coletas.0.amostras.0.parametros_campo.0.nome'));
    }

    /** RN39: token vencido ou revogado não envia; a coleta de outro coletor é recusada (RN36). */
    public function test_rn39_acesso_do_aparelho(): void
    {
        $token = $this->token();
        Dispositivo::query()->update(['expira_em' => now()->subMinute()]);
        $this->enviar($this->registro(), $token)->assertUnauthorized()->assertJson(['codigo' => 'ACESSO_VENCIDO']);

        $outro = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();
        $this->enviar($this->registro(), $this->token($outro))->assertForbidden();
        $this->assertSame(Coleta::AGENDADA, $this->coleta->fresh()->situacao);

        $analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create();
        $this->withToken($this->token($analista))->getJson('/api/campo/coletas')->assertForbidden();
    }

    /** RN44: localização a mais de 200 m do ponto marca a coleta para conferência. */
    public function test_rn44_localizacao_distante_marca_para_conferencia(): void
    {
        $this->reservatorio->update(['latitude' => -22.9056, 'longitude' => -47.0608]);
        $this->enviar($this->registro(['latitude' => -22.9156, 'longitude' => -47.0608]))->assertOk();

        $this->coleta->refresh();
        $this->assertTrue($this->coleta->conferir_localizacao);
        $this->assertGreaterThan(1000, $this->coleta->distancia_ponto_metros);
    }

    /** RN42: coleta passada a outro coletor → conflito; data futura → registro com problema (422), nada gravado. */
    public function test_rn42_coleta_passada_a_outro_coletor_e_dados_invalidos(): void
    {
        $this->enviar($this->registro(['data_hora_coleta' => now()->addHour()->toIso8601String()]))
            ->assertUnprocessable()->assertJsonValidationErrors('data_hora_coleta');
        $this->assertSame(0, Sincronizacao::count());

        $outro = Usuario::factory()->comPerfis(Perfis::COLETOR)->create();
        Auth::setUser($this->recepcao);
        app(AgendamentoColetas::class)->reagendar($this->coleta, ['coletor_id' => $outro->id, 'data' => today()->toDateString(),
            'periodo' => 'MANHA', 'motivo' => 'Troca de coletor']);
        Auth::logout();

        $this->enviar($this->registro())->assertOk()->assertJson(['situacao' => 'CONFLITO']);
        $this->assertSame('a coleta foi passada a outro coletor.', Sincronizacao::firstOrFail()->motivo_conflito);
    }
}
