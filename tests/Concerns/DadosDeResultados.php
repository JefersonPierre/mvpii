<?php

namespace Tests\Concerns;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Configuracao;
use App\Models\Legislacao;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\Usuario;
use App\Services\RecebimentoAmostras;
use App\Services\RegistroColetas;
use App\Support\Perfis;
use Illuminate\Support\Facades\Auth;

/**
 * Catálogo com limites e uma amostra recebida, para os testes do Entregável 3. O pacote "Potabilidade básica" do
 * orçamento passa a apontar para um pacote real com pH, turbidez, coliformes e cor; o item avulso é o cloro.
 */
trait DadosDeResultados
{
    use DadosDeColeta;

    protected Legislacao $portaria888;

    /** @var array<string, Parametro> */
    protected array $parametros = [];

    protected Usuario $analista;

    protected function criarCatalogoComLimites(int $quantidade = 2): void
    {
        $this->criarDadosDeColeta(quantidade: $quantidade);
        $p = fn (string $nome, string $unidade, array $extra = []) => Parametro::factory()->create(['nome' => $nome, 'unidade' => $unidade, ...$extra]);
        $this->parametros = [
            'ph' => $p('pH', 'adimensional', ['medido_em_campo' => true]),
            'turbidez' => $p('Turbidez', 'uT', ['limite_quantificacao' => 0.1]),
            'coliformes' => $p('Coliformes totais', 'NMP/100 mL', ['categoria' => 'MICROBIOLOGICA']),
            'cor' => $p('Cor aparente', 'uH'),
            'cloro' => $p('Cloro residual livre', 'mg/L', ['medido_em_campo' => true]),
        ];
        $pacote = Pacote::factory()->create(['nome' => 'Potabilidade básica', 'tipo_amostra_id' => $this->agua->id]);
        $pacote->parametros()->sync(collect($this->parametros)->except('cloro')->pluck('id'));
        $this->pacote->forceFill(['pacote_id' => $pacote->id])->save();
        $this->cloro->forceFill(['parametro_id' => $this->parametros['cloro']->id])->save();

        $this->portaria888 = Legislacao::factory()->create(['nome' => 'Portaria GM/MS nº 888/2021', 'inicio_vigencia' => '2021-05-07']);
        $this->portaria888->tiposAmostra()->sync([$this->agua->id]);
        // Sem limite para a cor: "Sem limite" (RN52).
        foreach ([['ph', 'FAIXA', 6, 9], ['turbidez', 'MAXIMO', null, 5], ['coliformes', 'AUSENCIA', null, null], ['cloro', 'FAIXA', 0.2, 5]] as [$chave, $tipo, $min, $max]) {
            $this->portaria888->limites()->create(['parametro_id' => $this->parametros[$chave]->id, 'tipo_amostra_id' => $this->agua->id,
                'tipo' => $tipo, 'valor_minimo' => $min, 'valor_maximo' => $max]);
        }

        Configuracao::atual()->update(['laboratorio_nome' => 'Laboratório IQA', 'laboratorio_latitude' => -22.9056, 'laboratorio_longitude' => -47.0608,
            'responsavel_tecnico' => 'Responsável Técnico', 'registro_profissional' => 'CRQ IV 00000000']);
        $this->analista = Usuario::factory()->comPerfis(Perfis::ANALISTA)->create(['nome' => 'Ana Analista']);
    }

    /** Coleta de hoje registrada pelo coletor (pH medido em campo) e amostras recebidas por outro analista. */
    protected function amostrasRecebidas(int $quantas = 1): Coleta
    {
        $pontos = array_slice([$this->reservatorio, $this->portaria], 0, $quantas);
        Auth::setUser(Usuario::factory()->comPerfis(Perfis::RECEPCAO)->create());
        $coleta = $this->agendar($pontos, ['data' => today()->toDateString()]);

        Auth::setUser($this->coletor);
        app(RegistroColetas::class)->registrar($coleta, [
            'data_hora_coleta' => now()->subHours(3)->format('Y-m-d H:i'),
            'amostras' => $coleta->amostras->mapWithKeys(fn (Amostra $a) => [$a->id => ['situacao' => Amostra::COLETADA,
                'temperatura' => '6.5', 'medicoes' => [$this->parametros['ph']->id => '7.2']]])->all(),
        ]);

        Auth::setUser(Usuario::factory()->comPerfis(Perfis::ANALISTA)->create(['nome' => 'Rui Recebimento']));
        app(RecebimentoAmostras::class)->registrar($coleta->amostras()->get(), [
            'recebida_em' => now()->subHour()->format('Y-m-d H:i'), 'temperatura_recebimento' => '5', 'decisao' => 'ACEITAR',
            'frasco_integro' => true, 'identificacao_legivel' => true, 'volume_suficiente' => true, 'preservacao_adequada' => true,
        ]);
        Auth::logout();

        return $coleta->fresh('amostras');
    }

    /** Resultados completos e conformes para enviar à revisão. */
    protected function resultadosConformes(array $alterar = []): array
    {
        $r = [
            $this->parametros['ph']->id => ['valor' => '7,2'],
            $this->parametros['turbidez']->id => ['valor' => '0,8'],
            $this->parametros['coliformes']->id => ['presenca' => 'AUSENTE'],
            $this->parametros['cor']->id => ['valor' => '5'],
            $this->parametros['cloro']->id => ['valor' => '1,1'],
        ];
        foreach ($alterar as $chave => $linha) {
            $r[$this->parametros[$chave]->id] = $linha;
        }

        return collect($r)->map(fn ($l) => ['data_analise' => today()->toDateString(), ...$l])->all();
    }
}
