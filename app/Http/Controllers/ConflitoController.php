<?php

namespace App\Http\Controllers;

use App\Models\Parametro;
use App\Models\Sincronizacao;
use App\Services\SincronizacaoColetas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC23 – Resolver conflito de sincronização (RF42, RN42, RN43): Recepção ou Administrador.
class ConflitoController extends Controller
{
    public function index(Request $request): View
    {
        $pendentes = $request->query('situacao') !== 'resolvidos';
        $conflitos = Sincronizacao::with(['coleta.orcamento.cliente', 'usuario', 'resolvidaPor'])
            ->when($pendentes, fn ($q) => $q->where('situacao', Sincronizacao::CONFLITO),
                fn ($q) => $q->whereIn('situacao', [Sincronizacao::ACEITA, Sincronizacao::DESCARTADA]))
            ->orderByDesc('recebida_em')->paginate(25)->withQueryString();

        return view('conflitos.index', compact('conflitos', 'pendentes'));
    }

    public function show(Sincronizacao $sincronizacao): View
    {
        abort_if($sincronizacao->situacao === Sincronizacao::APLICADA && $sincronizacao->motivo_conflito === null, 404);
        $sincronizacao->load(['coleta.orcamento.cliente', 'coleta.coletor', 'coleta.amostras.pontoColeta', 'usuario', 'resolvidaPor', 'fotos']);

        $ids = collect($sincronizacao->dados['amostras'] ?? [])->flatMap(fn ($a) => array_keys($a['medicoes'] ?? []))->unique();

        return view('conflitos.show', ['conflito' => $sincronizacao, 'parametros' => Parametro::whereKey($ids)->get()->keyBy('id')]);
    }

    public function aceitar(Sincronizacao $sincronizacao, SincronizacaoColetas $servico): RedirectResponse
    {
        $servico->aceitar($sincronizacao);

        return redirect()->route('coletas.show', $sincronizacao->coleta_id)
            ->with('sucesso', 'Registro do celular aceito: a coleta foi registrada como feita pelo coletor.');
    }

    public function descartar(Request $request, Sincronizacao $sincronizacao, SincronizacaoColetas $servico): RedirectResponse
    {
        $dados = $request->validate(['motivo_descarte' => ['required', 'string', 'max:255']],
            ['motivo_descarte.required' => 'Informe o motivo do descarte.']);
        $servico->descartar($sincronizacao, $dados['motivo_descarte']);

        return redirect()->route('conflitos.index')->with('sucesso', 'Registro descartado. A coleta não mudou e o registro continua guardado.');
    }
}
