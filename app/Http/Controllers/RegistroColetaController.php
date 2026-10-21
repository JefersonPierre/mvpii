<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroColetaRequest;
use App\Models\Coleta;
use App\Services\RegistroColetas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// UC16 – Registrar coleta (RF33, RN32): pelo coletor responsável ou por um Administrador.
class RegistroColetaController extends Controller
{
    public function create(Coleta $coleta): View|RedirectResponse
    {
        Gate::authorize('registrar', $coleta);
        if (! $coleta->podeRegistrar()) {
            return redirect()->route('coletas.show', $coleta)->with('erro', 'Só a coleta Agendada pode ser registrada.');
        }
        $coleta->load(['orcamento.cliente', 'coletor', 'amostras.pontoColeta', 'amostras.itens']);

        return view('coletas.registro', compact('coleta'));
    }

    public function store(RegistroColetaRequest $request, Coleta $coleta, RegistroColetas $registro): RedirectResponse
    {
        Gate::authorize('registrar', $coleta);

        if ($request->naoRealizada()) {
            $registro->registrarNaoRealizada($coleta, $request->validated());

            return redirect()->route('coletas.show', $coleta)
                ->with('sucesso', 'Coleta registrada como Não realizada. As amostras voltaram ao saldo do orçamento para reagendamento.');
        }

        $registro->registrar($coleta, $request->validated());
        $naoColetadas = collect($request->validated('amostras'))->where('situacao', 'NAO_COLETADA')->count();

        return redirect()->route('coletas.show', $coleta)->with('sucesso', 'Coleta registrada como Realizada.'
            .($naoColetadas ? " {$naoColetadas} amostra(s) não coletada(s) voltaram ao saldo do orçamento." : ''));
    }
}
