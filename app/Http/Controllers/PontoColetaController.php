<?php

namespace App\Http\Controllers;

use App\Http\Requests\PontoColetaRequest;
use App\Models\Cliente;
use App\Models\PontoColeta;
use App\Models\TipoAmostra;
use App\Services\CadastroPontos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC04 – Cadastrar ponto de coleta (RF09, RF10, RN03, RN04). A lista (RF11) fica na ficha do cliente.
class PontoColetaController extends Controller
{
    public function __construct(private readonly CadastroPontos $cadastro) {}

    public function create(Cliente $cliente): View|RedirectResponse
    {
        if ($bloqueio = $this->clienteInativo($cliente)) {
            return $bloqueio;
        }

        return $this->formulario($cliente, new PontoColeta);
    }

    public function store(PontoColetaRequest $request, Cliente $cliente): RedirectResponse
    {
        if ($bloqueio = $this->clienteInativo($cliente)) {
            return $bloqueio;
        }
        $this->cadastro->salvar($cliente, new PontoColeta, $request->validated());

        return $this->voltarParaFicha($cliente, 'Ponto de coleta cadastrado.');
    }

    public function edit(PontoColeta $ponto): View
    {
        return $this->formulario($ponto->cliente, $ponto);
    }

    public function update(PontoColetaRequest $request, PontoColeta $ponto): RedirectResponse
    {
        $this->cadastro->salvar($ponto->cliente, $ponto, $request->validated());

        return $this->voltarParaFicha($ponto->cliente, 'Ponto de coleta atualizado.');
    }

    public function situacao(Request $request, PontoColeta $ponto): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        if ($ativo && ! $ponto->cliente->ativo) {
            return $this->voltarParaFicha($ponto->cliente, null)->with('erro', 'Reative o cliente antes de reativar o ponto de coleta.');
        }
        $this->cadastro->alterarSituacao($ponto, $ativo);

        return $this->voltarParaFicha($ponto->cliente, $ativo ? 'Ponto de coleta reativado.' : 'Ponto de coleta inativado.');
    }

    private function formulario(Cliente $cliente, PontoColeta $ponto): View
    {
        // Tipos ativos; na edição, também o tipo atual do ponto, mesmo que tenha sido inativado.
        $tipos = TipoAmostra::query()
            ->where(fn ($q) => $q->where('ativo', true)->orWhere('id', $ponto->tipo_amostra_id))
            ->orderByNome()->get();

        return view('pontos.form', compact('cliente', 'ponto', 'tipos'));
    }

    /** RN04: cliente inativo não recebe novos pontos de coleta. */
    private function clienteInativo(Cliente $cliente): ?RedirectResponse
    {
        return $cliente->ativo ? null
            : $this->voltarParaFicha($cliente, null)->with('erro', 'Cliente inativo: reative o cliente para cadastrar pontos de coleta.');
    }

    private function voltarParaFicha(Cliente $cliente, ?string $mensagem): RedirectResponse
    {
        $resposta = redirect()->route('clientes.show', [$cliente, 'aba' => 'pontos']);

        return $mensagem ? $resposta->with('sucesso', $mensagem) : $resposta;
    }
}
