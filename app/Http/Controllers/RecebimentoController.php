<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecebimentoRequest;
use App\Models\Amostra;
use App\Models\Configuracao;
use App\Services\RecebimentoAmostras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC17 – Registrar recebimento da amostra (RF34, RN33–RN35)
class RecebimentoController extends Controller
{
    public function __construct(private readonly RecebimentoAmostras $recebimento) {}

    /** Amostras a receber; o analista informa o código ou marca as amostras (UC17 1, 1a). */
    public function create(Request $request): View
    {
        $relacoes = ['coleta.orcamento.cliente', 'coleta.coletor', 'pontoColeta', 'itens', 'coletadaPor'];
        $aReceber = Amostra::with($relacoes)->where('amostras.situacao', Amostra::COLETADA)
            ->join('coletas', 'coletas.id', '=', 'amostras.coleta_id')
            ->orderBy('coletas.data_hora_coleta')->orderBy('amostras.sequencia')
            ->select('amostras.*')->get();

        $codigo = mb_strtoupper(trim((string) $request->query('codigo')));
        $ids = array_map('intval', (array) $request->query('amostras', []));
        $aviso = null;
        if ($codigo !== '') {
            // UC17 2a: código inexistente ou amostra que não está Coletada.
            $encontrada = Amostra::where('codigo', $codigo)->first();
            $aviso = match (true) {
                ! $encontrada => "Amostra {$codigo} não encontrada.",
                $encontrada->situacao !== Amostra::COLETADA => "A amostra {$codigo} está {$encontrada->nomeSituacao()} e não pode ser recebida.",
                default => null,
            };
            $ids = $encontrada && ! $aviso ? [$encontrada->id] : [];
        }
        $selecionadas = $aReceber->whereIn('id', old('amostras', $ids))->values();

        return view('amostras.receber', [
            'aReceber' => $aReceber,
            'selecionadas' => $selecionadas,
            'codigo' => $codigo,
            'aviso' => $aviso,
            'config' => Configuracao::atual(),
        ]);
    }

    public function store(RecebimentoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $amostras = Amostra::whereIn('id', $dados['amostras'])->orderBy('sequencia')->get();
        $this->recebimento->registrar($amostras, $dados);

        $codigos = $amostras->pluck('codigo')->implode(', ');
        $mensagem = $dados['decisao'] === 'ACEITAR'
            ? "Recebimento registrado: {$codigos} pronta(s) para análise."
            : "Amostra(s) {$codigos} rejeitada(s). Voltaram ao saldo do orçamento para nova coleta.";

        return redirect()->route('amostras.receber')->with('sucesso', $mensagem);
    }
}
