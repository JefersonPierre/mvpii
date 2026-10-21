<?php

namespace App\Http\Controllers;

use App\Models\Amostra;
use App\Services\Rastreabilidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// UC18 – Consultar amostras (RF36, RN33, RN36): pesquisa e linha do tempo.
class AmostraController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = [
            'codigo' => mb_strtoupper(trim((string) $request->query('codigo'))),
            'cliente' => trim((string) $request->query('cliente')),
            'ponto' => trim((string) $request->query('ponto')),
            'de' => $request->date('de')?->toDateString(),
            'ate' => $request->date('ate')?->toDateString(),
            'situacao' => array_key_exists($request->query('situacao'), Amostra::SITUACOES) ? $request->query('situacao') : '',
        ];
        $termo = fn (string $texto) => '%'.mb_strtolower($texto).'%';

        $amostras = Amostra::with(['coleta.orcamento.cliente', 'pontoColeta'])
            ->visiveisPara($request->user())
            ->when($filtros['codigo'], fn ($q, $c) => $q->where('codigo', 'like', "%{$c}%"))
            ->when($filtros['cliente'], fn ($q, $c) => $q->whereHas('coleta.orcamento.cliente', fn ($q) => $q->whereRaw('LOWER(nome) LIKE ?', [$termo($c)])))
            ->when($filtros['ponto'], fn ($q, $p) => $q->whereHas('pontoColeta', fn ($q) => $q->whereRaw('LOWER(identificacao) LIKE ?', [$termo($p)])))
            ->when($filtros['de'], fn ($q, $d) => $q->whereHas('coleta', fn ($q) => $q->whereDate('data', '>=', $d)))
            ->when($filtros['ate'], fn ($q, $d) => $q->whereHas('coleta', fn ($q) => $q->whereDate('data', '<=', $d)))
            ->when($filtros['situacao'], fn ($q, $s) => $q->where('situacao', $s))
            ->orderByDesc('ano')->orderByDesc('sequencia')
            ->paginate(25)->withQueryString();

        return view('amostras.index', compact('amostras', 'filtros'));
    }

    public function show(Amostra $amostra, Rastreabilidade $rastreio): View
    {
        Gate::authorize('view', $amostra);
        $amostra->load(['coleta.orcamento.cliente', 'coleta.coletor', 'pontoColeta', 'itens', 'coletadaPor', 'recebidaPor']);

        return view('amostras.show', ['amostra' => $amostra, 'etapas' => $rastreio->linhaDoTempo($amostra)]);
    }
}
