<?php

namespace Database\Seeders;

use App\Models\Amostra;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Services\CadastroOrcamentos;
use App\Services\RegistroColetas;
use App\Support\Perfis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Entregável 2 de exemplo: um usuário por perfil, para demonstrar cada visão do sistema, e um orçamento aprovado com
 * uma coleta realizada (amostras a receber) e outra agendada. Roda depois do AtendimentoExemploSeeder.
 * Pode ser executado mais de uma vez: se o coletor de exemplo já existe, não faz nada.
 */
class ColetasExemploSeeder extends Seeder
{
    public const USUARIOS = [
        ['nome' => 'Rita Recepção', 'email' => 'recepcao@laboratorio.local', 'perfil' => Perfis::RECEPCAO],
        ['nome' => 'Carlos Coletor', 'email' => 'coletor@laboratorio.local', 'perfil' => Perfis::COLETOR],
        ['nome' => 'Ana Analista', 'email' => 'analista@laboratorio.local', 'perfil' => Perfis::ANALISTA],
    ];

    public function run(CadastroOrcamentos $orcamentos, AgendamentoColetas $agendamento, RegistroColetas $registro): void
    {
        $cliente = Cliente::where('documento', '11222333000181')->first();
        $pacote = Pacote::where('nome', 'Potabilidade básica')->first();
        $cloro = Parametro::where('nome', 'Cloro residual livre')->first();
        if (! $cliente || ! $pacote || Usuario::where('email', 'coletor@laboratorio.local')->exists()) {
            return;
        }

        // Mesma senha do administrador inicial (definida no .env).
        $usuarios = [];
        foreach (self::USUARIOS as $u) {
            $usuarios[$u['perfil']] = Usuario::create(['nome' => $u['nome'], 'email' => $u['email'], 'senha' => config('laboratorio.admin.senha')]);
            $usuarios[$u['perfil']]->definirPerfis([$u['perfil']]);
        }

        $portaria = $cliente->pontosColeta()->create([
            'identificacao' => 'Torneira da portaria',
            'tipo_amostra_id' => $pacote->tipo_amostra_id,
            'logradouro' => 'Rua Barão de Jaguara',
            'numero' => '1000',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
            'referencia' => 'Torneira ao lado da guarita',
            'latitude' => -22.9038,
            'longitude' => -47.0595,
        ]);
        $reservatorio = $cliente->pontosColeta()->where('identificacao', 'Reservatório superior – Bloco A')->firstOrFail();

        // As operações passam pelos mesmos serviços das telas, que contam com o $fillable.
        Model::reguard();

        // Orçamento aprovado pela Recepção: pacote × 4 e cloro × 4.
        Auth::setUser($usuarios[Perfis::RECEPCAO]);
        $orcamento = $orcamentos->salvarRascunho(new Orcamento, [
            'cliente_id' => $cliente->id,
            'contato_id' => $cliente->contatoPrincipal->id,
            'tipo_amostra_id' => $pacote->tipo_amostra_id,
            'itens' => array_values(array_filter([
                ['referencia' => 'PACOTE:'.$pacote->id, 'quantidade' => 4],
                $cloro ? ['referencia' => 'PARAMETRO:'.$cloro->id, 'quantidade' => 4] : null,
            ])),
            'taxa_coleta' => 80,
            'desconto_percentual' => 0,
            'validade_dias' => 30,
            'condicoes_pagamento' => 'Boleto em 30 dias',
            'observacoes' => 'Monitoramento trimestral.',
        ]);
        $orcamentos->mudarSituacao($orcamento, Orcamento::ENVIADO, ['data_envio' => now()->subDays(3), 'valido_ate' => today()->addDays(27)]);
        $orcamentos->mudarSituacao($orcamento, Orcamento::APROVADO, ['data_resposta' => today()->subDay()]);

        $itens = $orcamento->itens()->pluck('id')->all();
        $amostras = fn (array $pontos) => array_map(fn ($p) => ['ponto_coleta_id' => $p->id, 'itens' => $itens], $pontos);

        // Coleta de hoje, já realizada pelo coletor: as amostras aguardam o recebimento pelo analista.
        $hoje = $agendamento->agendar($orcamento, [
            'coletor_id' => $usuarios[Perfis::COLETOR]->id, 'data' => today()->toDateString(), 'periodo' => 'MANHA',
            'horario' => '08:30', 'instrucoes' => 'Procurar o zelador na portaria. Levar frasco com tiossulfato.',
            'amostras' => $amostras([$reservatorio, $portaria]),
        ]);
        Auth::setUser($usuarios[Perfis::COLETOR]);
        $registro->registrar($hoje, [
            'data_hora_coleta' => now()->subHours(2)->format('Y-m-d H:i'),
            'condicoes_tempo' => 'Ensolarado, sem chuva nas últimas 24 h',
            'amostras' => $hoje->amostras->mapWithKeys(fn (Amostra $a) => [$a->id => ['situacao' => Amostra::COLETADA, 'temperatura' => '6.5']])->all(),
        ]);

        // Próxima coleta, agendada para daqui a uma semana.
        Auth::setUser($usuarios[Perfis::RECEPCAO]);
        $agendamento->agendar($orcamento, [
            'coletor_id' => $usuarios[Perfis::COLETOR]->id, 'data' => today()->addWeek()->toDateString(), 'periodo' => 'TARDE',
            'horario' => null, 'instrucoes' => null,
            'amostras' => $amostras([$reservatorio]),
        ]);
        Auth::logout();
    }
}
