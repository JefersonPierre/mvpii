<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResultadoRequest;
use App\Models\Amostra;
use App\Services\ResultadosAmostras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC24 – Lançar resultados (RF48–RF50) e UC25 – Revisar e liberar resultados (RF51, RN53).
class ResultadoController extends Controller
{
    public function __construct(private readonly ResultadosAmostras $resultados) {}

    /** Resultados > A lançar e A revisar. A revisão lista só as amostras que o usuário pode liberar (UC25 1a). */
    public function index(Request $request): View
    {
        $aba = $request->query('aba') === 'revisar' ? 'revisar' : 'lancar';
        $usuario = $request->user();
        $base = fn () => Amostra::with(['coleta.orcamento.cliente', 'pontoColeta'])->orderBy('recebida_em');

        $aLancar = $base()->whereIn('situacao', Amostra::RECEBEM_RESULTADOS)->get();
        $aRevisar = $base()->where('situacao', Amostra::EM_REVISAO)
            ->whereDoesntHave('resultados', fn ($q) => $q->where('lancado_por_id', $usuario->id))->get();

        return view('resultados.index', compact('aba', 'aLancar', 'aRevisar'));
    }

    public function edit(Amostra $amostra): View|RedirectResponse
    {
        if (! in_array($amostra->situacao, Amostra::RECEBEM_RESULTADOS, true)) {
            return redirect()->route('amostras.show', $amostra)->with('erro', 'Só amostras Recebidas ou Em análise recebem resultados (RN50).');
        }
        $amostra->load(['coleta.orcamento.cliente', 'coleta.orcamento.tipoAmostra', 'pontoColeta', 'legislacao']);
        $legislacoes = $this->resultados->legislacoesAplicaveis($amostra);
        $legislacao = $amostra->legislacao ?? ($legislacoes->count() === 1 ? $legislacoes->first() : null);
        $escolhida = old('legislacao_id') ? $legislacoes->firstWhere('id', (int) old('legislacao_id')) : null;

        return view('resultados.form', [
            'amostra' => $amostra,
            'legislacoes' => $legislacoes,
            'legislacao' => $legislacao ?? $escolhida,
            'escolher' => ! $amostra->legislacao_id && $legislacoes->count() > 1,
            'linhas' => $this->resultados->linhas($amostra, $legislacao ?? $escolhida ?? $legislacoes->first()),
        ]);
    }

    public function update(ResultadoRequest $request, Amostra $amostra): RedirectResponse
    {
        $this->resultados->salvar($amostra, $request->validated(), $request->enviarParaRevisao());

        return $request->enviarParaRevisao()
            ? redirect()->route('resultados.index')->with('sucesso', "Resultados da amostra {$amostra->codigo} enviados para revisão.")
            : redirect()->route('resultados.edit', $amostra)->with('sucesso', 'Resultados salvos. A amostra continua Em análise.');
    }

    public function revisao(Request $request, Amostra $amostra): View|RedirectResponse
    {
        if ($amostra->situacao !== Amostra::EM_REVISAO) {
            return redirect()->route('amostras.show', $amostra)->with('erro', 'Esta amostra não está Em revisão.');
        }
        $amostra->load(['coleta.orcamento.cliente', 'coleta.coletor', 'pontoColeta', 'legislacao']);

        return view('resultados.revisao', [
            'amostra' => $amostra,
            'linhas' => $this->resultados->linhas($amostra, $amostra->legislacao),
            'podeLiberar' => $this->resultados->podeLiberar($amostra, $request->user()),
        ]);
    }

    public function liberar(Amostra $amostra): RedirectResponse
    {
        $this->resultados->liberar($amostra);

        return redirect()->route('resultados.index', ['aba' => 'revisar'])
            ->with('sucesso', "Resultados da amostra {$amostra->codigo} liberados. A amostra está pronta para o laudo.");
    }

    public function devolver(Request $request, Amostra $amostra): RedirectResponse
    {
        $dados = $request->validate(['motivo_devolucao' => ['required', 'string', 'max:255']],
            ['motivo_devolucao.required' => 'Informe o motivo da devolução.']);
        $this->resultados->devolver($amostra, $dados['motivo_devolucao']);

        return redirect()->route('resultados.index', ['aba' => 'revisar'])
            ->with('sucesso', "Amostra {$amostra->codigo} devolvida para correção.");
    }
}
