<?php

namespace Tests\Concerns;

use App\Models\Cliente;
use App\Models\Coleta;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\PontoColeta;
use App\Models\TipoAmostra;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Support\Perfis;

/** Orçamento aprovado, pontos de coleta e coletor para os testes do Entregável 2. */
trait DadosDeColeta
{
    protected Cliente $cliente;

    protected TipoAmostra $agua;

    protected Orcamento $orcamento;

    protected OrcamentoItem $pacote;

    protected OrcamentoItem $cloro;

    protected PontoColeta $reservatorio;

    protected PontoColeta $portaria;

    protected Usuario $coletor;

    /** Orçamento Aprovado com "Potabilidade básica" × 2 e "Cloro residual livre" × 2, e dois pontos de água potável. */
    protected function criarDadosDeColeta(string $situacao = Orcamento::APROVADO, int $quantidade = 2): void
    {
        $this->cliente = Cliente::factory()->create(['nome' => 'Residencial Primavera']);
        $this->agua = TipoAmostra::factory()->create(['nome' => 'Água potável']);
        $this->orcamento = Orcamento::factory()->create([
            'cliente_id' => $this->cliente->id, 'tipo_amostra_id' => $this->agua->id, 'situacao' => $situacao,
            'contato_id' => $this->cliente->contatoPrincipal->id, 'data_resposta' => today(),
        ]);
        $this->pacote = $this->orcamento->itens()->create(['tipo' => 'PACOTE', 'descricao' => 'Pacote Potabilidade básica',
            'preco_unitario' => 150, 'quantidade' => $quantidade, 'subtotal' => 150 * $quantidade, 'ordem' => 0]);
        $this->cloro = $this->orcamento->itens()->create(['tipo' => 'PARAMETRO', 'descricao' => 'Cloro residual livre',
            'preco_unitario' => 40, 'quantidade' => $quantidade, 'subtotal' => 40 * $quantidade, 'ordem' => 1]);
        $this->reservatorio = PontoColeta::factory()->create(['cliente_id' => $this->cliente->id, 'tipo_amostra_id' => $this->agua->id,
            'identificacao' => 'Reservatório superior – Bloco A']);
        $this->portaria = PontoColeta::factory()->create(['cliente_id' => $this->cliente->id, 'tipo_amostra_id' => $this->agua->id,
            'identificacao' => 'Torneira da portaria']);
        $this->coletor = Usuario::factory()->comPerfis(Perfis::COLETOR)->create(['nome' => 'Carlos Coletor']);
    }

    /** Dados do formulário de agendamento: uma amostra por ponto informado, com os dois itens. */
    protected function dadosAgendamento(array $pontos, array $alterar = []): array
    {
        return [
            'data' => today()->addDays(2)->toDateString(),
            'periodo' => 'MANHA',
            'horario' => '09:00',
            'coletor_id' => $this->coletor->id,
            'instrucoes' => 'Procurar o zelador na portaria.',
            'amostras' => array_map(fn (PontoColeta $p) => ['ponto_coleta_id' => $p->id, 'itens' => [$this->pacote->id, $this->cloro->id]], $pontos),
            ...$alterar,
        ];
    }

    /** Agenda direto pelo serviço (sem passar pela tela), como a Recepção. */
    protected function agendar(array $pontos, array $alterar = []): Coleta
    {
        return app(AgendamentoColetas::class)->agendar($this->orcamento, $this->dadosAgendamento($pontos, $alterar));
    }
}
