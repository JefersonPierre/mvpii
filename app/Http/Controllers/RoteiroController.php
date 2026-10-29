<?php

namespace App\Http\Controllers;

use App\Models\Coleta;
use App\Models\Configuracao;
use App\Models\Usuario;
use App\Services\AgendamentoColetas;
use App\Services\Roteirizacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// UC20 – Consultar roteiro de coletas (RF44–RF46, RN36, RN47, RN48).
class RoteiroController extends Controller
{
    public function index(Request $request, Roteirizacao $roteiro, AgendamentoColetas $agendamento): View
    {
        $usuario = $request->user();
        $data = $request->date('data')?->toDateString() ?? today()->toDateString();
        // RN36: quem só tem o perfil Coletor vê o próprio roteiro.
        $coletores = $usuario->veTodasAsColetas() ? $agendamento->coletores() : collect([$usuario]);
        $coletorId = $usuario->veTodasAsColetas() ? ($request->integer('coletor') ?: $coletores->first()?->id) : $usuario->id;
        $coletor = $coletores->firstWhere('id', $coletorId) ?? ($coletorId ? Usuario::find($coletorId) : null);

        $coletas = $coletor ? $roteiro->coletasDoDia($coletor->id, $data) : collect();
        $config = Configuracao::atual();

        return view('coletas.roteiro', [
            'data' => $data,
            'coletores' => $coletores,
            'coletor' => $coletor,
            'coletas' => $coletas,
            'distancias' => $coletor ? $roteiro->distancias($coletas) : [],
            'links' => $coletor ? $roteiro->links($coletas) : ['google' => [], 'waze' => null],
            'config' => $config,
            'podeOrdenar' => Gate::allows('coletas.roteiro.ordenar') && ($usuario->veTodasAsColetas() || $coletor?->id === $usuario->id),
        ]);
    }

    /** UC20 1a: sobe ou desce a coleta na ordem do dia. */
    public function mover(Request $request, Coleta $coleta, Roteirizacao $roteiro): RedirectResponse
    {
        Gate::authorize('view', $coleta);
        $roteiro->mover($coleta, $request->input('direcao') === 'subir' ? 'subir' : 'descer');

        return redirect()->route('coletas.roteiro', ['coletor' => $coleta->coletor_id, 'data' => $coleta->data->toDateString()])
            ->with('sucesso', 'Ordem de visita alterada para este dia.');
    }
}
