<?php

namespace Tests\Feature;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Support\Perfis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\DadosDeColeta;
use Tests\TestCase;

// UC16 – Registrar coleta (RF33, RN28, RN31, RN32, RN33, RN36)
class RegistroColetaTest extends TestCase
{
    use DadosDeColeta, RefreshDatabase;

    private Coleta $coleta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $this->criarDadosDeColeta();
        $this->coleta = $this->agendar([$this->reservatorio, $this->portaria], ['data' => today()->toDateString()]);
        $this->actingAs($this->coletor);
    }

    private function amostra(int $i): Amostra
    {
        return $this->coleta->amostras[$i];
    }

    /** CT20: 1 amostra coletada e 1 não coletada com motivo → coleta Realizada e saldo de 1 amostra devolvido. */
    public function test_ct20_registra_coleta_com_amostra_nao_coletada(): void
    {
        $this->get(route('coletas.registro', $this->coleta))->assertOk()->assertSee($this->amostra(0)->codigo);

        $this->post(route('coletas.registro', $this->coleta), [
            'acao' => 'realizada',
            'data_hora_coleta' => now()->subHour()->format('Y-m-d\TH:i'),
            'condicoes_tempo' => 'Ensolarado',
            'amostras' => [
                $this->amostra(0)->id => ['situacao' => 'COLETADA', 'temperatura' => '6,5', 'observacoes' => 'Água límpida'],
                $this->amostra(1)->id => ['situacao' => 'NAO_COLETADA', 'motivo' => 'SEM_AGUA', 'observacoes' => 'Manutenção'],
            ],
        ])->assertRedirect(route('coletas.show', $this->coleta))->assertSessionHasNoErrors();

        $this->coleta->refresh();
        $this->assertSame([Coleta::REALIZADA, $this->coletor->id], [$this->coleta->situacao, $this->coleta->registrada_por_id]);
        $this->assertSame([Amostra::COLETADA, '6.5'], [$this->amostra(0)->fresh()->situacao, $this->amostra(0)->fresh()->temperatura_coleta]);
        $this->assertSame([Amostra::NAO_COLETADA, 'SEM_AGUA'], [$this->amostra(1)->fresh()->situacao, $this->amostra(1)->fresh()->motivo_nao_coleta]);

        $saldo = app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id];
        $this->assertSame([1, 1], [$saldo['coletadas'], $saldo['saldo']]);
    }

    /** CT21: data e hora futuras → não grava. */
    public function test_ct21_data_e_hora_futuras_nao_grava(): void
    {
        $this->post(route('coletas.registro', $this->coleta), [
            'acao' => 'realizada',
            'data_hora_coleta' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'amostras' => [
                $this->amostra(0)->id => ['situacao' => 'COLETADA'],
                $this->amostra(1)->id => ['situacao' => 'COLETADA'],
            ],
        ])->assertSessionHasErrors(['data_hora_coleta' => 'A data e a hora da coleta não podem ser futuras.']);

        $this->assertSame(Coleta::AGENDADA, $this->coleta->fresh()->situacao);
    }

    /** RN32: amostra não coletada exige motivo; coleta Realizada exige ao menos uma coletada. */
    public function test_rn32_motivo_e_ao_menos_uma_coletada(): void
    {
        $dados = ['acao' => 'realizada', 'data_hora_coleta' => now()->format('Y-m-d\TH:i')];

        $this->post(route('coletas.registro', $this->coleta), [...$dados, 'amostras' => [
            $this->amostra(0)->id => ['situacao' => 'COLETADA'],
            $this->amostra(1)->id => ['situacao' => 'NAO_COLETADA'],
        ]])->assertSessionHasErrors(["amostras.{$this->amostra(1)->id}.motivo" => 'Informe o motivo da amostra não coletada.']);

        $this->post(route('coletas.registro', $this->coleta), [...$dados, 'amostras' => [
            $this->amostra(0)->id => ['situacao' => 'NAO_COLETADA', 'motivo' => 'CLIENTE_AUSENTE'],
            $this->amostra(1)->id => ['situacao' => 'NAO_COLETADA', 'motivo' => 'CLIENTE_AUSENTE'],
        ]])->assertSessionHasErrors('amostras');

        $this->assertSame(Coleta::AGENDADA, $this->coleta->fresh()->situacao);
    }

    /** UC16 3b: coleta não realizada devolve o saldo e pode ser reagendada com os mesmos códigos (RN31). */
    public function test_coleta_nao_realizada_e_reagendada(): void
    {
        $this->post(route('coletas.registro', $this->coleta), ['acao' => 'nao_realizada', 'data_hora_coleta' => now()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['motivo_nao_realizada' => 'Informe o motivo da coleta não realizada.']);
        $this->post(route('coletas.registro', $this->coleta), ['acao' => 'nao_realizada', 'data_hora_coleta' => now()->format('Y-m-d\TH:i'),
            'motivo_nao_realizada' => 'CLIENTE_AUSENTE'])->assertSessionHasNoErrors();

        $this->assertSame(Coleta::NAO_REALIZADA, $this->coleta->fresh()->situacao);
        $this->assertSame([Amostra::NAO_COLETADA, Amostra::NAO_COLETADA], $this->coleta->amostras()->pluck('situacao')->all());
        $this->assertSame(2, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['saldo']);

        $this->actingAs(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $this->put(route('coletas.update', $this->coleta), ['data' => today()->addDay()->toDateString(), 'periodo' => 'MANHA',
            'coletor_id' => $this->coletor->id, 'motivo' => 'Cliente ausente; nova data combinada'])->assertSessionHasNoErrors();

        $this->assertSame(Coleta::AGENDADA, $this->coleta->fresh()->situacao);
        $this->assertSame([Amostra::PREVISTA, Amostra::PREVISTA], $this->coleta->amostras()->pluck('situacao')->all());
        $this->assertSame(0, app(AgendamentoColetas::class)->saldo($this->orcamento)[$this->pacote->id]['saldo']);
    }

    /** UC16 1a / RN36: coleta de outro coletor → acesso negado; a coleta Realizada não muda mais (RN31). */
    public function test_coleta_de_outro_coletor_e_coleta_realizada(): void
    {
        $this->actingAs(Usuario::factory()->comPerfis(Perfis::COLETOR)->create());
        $this->get(route('coletas.registro', $this->coleta))->assertForbidden();

        $this->actingAs(Usuario::factory()->comPerfis(Perfis::ANALISTA)->create());
        $this->get(route('coletas.registro', $this->coleta))->assertForbidden();

        // O Administrador também registra (RN32).
        $this->actingAs(Usuario::factory()->create());
        $this->post(route('coletas.registro', $this->coleta), ['acao' => 'realizada', 'data_hora_coleta' => now()->format('Y-m-d\TH:i'),
            'amostras' => [$this->amostra(0)->id => ['situacao' => 'COLETADA'], $this->amostra(1)->id => ['situacao' => 'COLETADA']]])
            ->assertSessionHasNoErrors();

        $this->get(route('coletas.registro', $this->coleta))->assertRedirect(route('coletas.show', $this->coleta));
        $this->get(route('coletas.edit', $this->coleta))->assertRedirect(route('coletas.show', $this->coleta));
    }
}
