<?php

namespace Database\Seeders;

use App\Models\Amostra;
use App\Models\Configuracao;
use App\Models\Orcamento;
use App\Models\Parametro;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Services\EmissaoLaudos;
use App\Services\RecebimentoAmostras;
use App\Services\RegistroColetas;
use App\Services\ResultadosAmostras;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

// E3 de exemplo: dados do laboratório para o laudo e uma coleta de dois dias atrás já com resultados liberados e laudo
// emitido, passando pelos mesmos serviços das telas (RN53: quem libera não é quem lançou).
class ResultadosExemploSeeder extends Seeder
{
    public function run(AgendamentoColetas $agendamento, RegistroColetas $registro, RecebimentoAmostras $recebimento,
        ResultadosAmostras $resultados, EmissaoLaudos $laudos): void
    {
        $config = Configuracao::atual();
        if (! $config->laudoConfigurado()) {
            $config->update([
                'laboratorio_nome' => $config->laboratorio_nome ?: 'Laboratório IQA – Análises de Água',
                'laboratorio_endereco' => $config->laboratorio_endereco ?: 'Av. Francisco Glicério, 1500 – Centro, Campinas/SP',
                'laboratorio_telefone' => $config->laboratorio_telefone ?: '1932310000',
                'laboratorio_latitude' => -22.9064,
                'laboratorio_longitude' => -47.0616,
                'responsavel_tecnico' => 'Mariana Souza',
                'registro_profissional' => 'CRQ-IV 04123456',
            ]);
        }

        $admin = Usuario::query()->orderBy('id')->first();
        $coletor = Usuario::where('email', 'coletor@laboratorio.local')->first();
        $analista = Usuario::where('email', 'analista@laboratorio.local')->first();
        $orcamento = Orcamento::where('situacao', Orcamento::APROVADO)->whereHas('coletas')->first();
        if (! $admin || ! $coletor || ! $analista || ! $orcamento || $orcamento->coletas()->whereDate('data', '<', today())->exists()) {
            return;
        }
        $reservatorio = $orcamento->cliente->pontosColeta()->where('identificacao', 'Reservatório superior – Bloco A')->firstOrFail();
        $dia = today()->subDays(2);

        Auth::setUser(Usuario::where('email', 'recepcao@laboratorio.local')->firstOrFail());
        $coleta = $agendamento->agendar($orcamento, [
            'coletor_id' => $coletor->id, 'data' => $dia->toDateString(), 'periodo' => 'MANHA', 'horario' => '09:00',
            'instrucoes' => null, 'amostras' => [['ponto_coleta_id' => $reservatorio->id, 'itens' => $orcamento->itens()->pluck('id')->all()]],
        ]);
        /** @var Amostra $amostra */
        $amostra = $coleta->amostras()->firstOrFail();
        $campo = Parametro::whereIn('nome', ['pH', 'Cloro residual livre'])->pluck('id', 'nome');

        Auth::setUser($coletor);
        $registro->registrar($coleta, [
            'data_hora_coleta' => $dia->copy()->setTime(9, 15)->format('Y-m-d H:i'),
            'condicoes_tempo' => 'Nublado',
            'amostras' => [$amostra->id => ['situacao' => Amostra::COLETADA, 'temperatura' => '5.5',
                'medicoes' => [$campo['pH'] => '7.2', $campo['Cloro residual livre'] => '1.1']]],
        ]);

        Auth::setUser($analista);
        $recebimento->registrar(collect([$amostra]), [
            'recebida_em' => $dia->copy()->setTime(11, 0)->format('Y-m-d H:i'), 'temperatura_recebimento' => '6.0', 'decisao' => 'ACEITAR',
            'frasco_integro' => true, 'identificacao_legivel' => true, 'volume_suficiente' => true, 'preservacao_adequada' => true,
        ]);
        $laboratorio = Parametro::whereIn('nome', ['Turbidez', 'Cor aparente', 'Coliformes totais', 'Escherichia coli'])->pluck('id', 'nome');
        $analise = $dia->copy()->addDay()->toDateString();
        $resultados->salvar($amostra, ['resultados' => [
            $laboratorio['Turbidez'] => ['valor' => '0.8', 'data_analise' => $analise],
            $laboratorio['Cor aparente'] => ['valor' => '5', 'data_analise' => $analise],
            $laboratorio['Coliformes totais'] => ['presenca' => 'AUSENTE', 'data_analise' => $analise],
            $laboratorio['Escherichia coli'] => ['presenca' => 'AUSENTE', 'data_analise' => $analise],
        ]], enviarParaRevisao: true);

        Auth::setUser($admin);
        $resultados->liberar($amostra);
        $laudos->emitir(collect([$amostra]));
        Auth::logout();
    }
}
