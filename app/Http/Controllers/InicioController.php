<?php

namespace App\Http\Controllers;

use App\Models\Orcamento;
use App\Services\CadastroOrcamentos;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Tela inicial: o atendimento começa pelo orçamento (seção 1 da documentação).
class InicioController extends Controller
{
    public function __invoke(CadastroOrcamentos $orcamentos): View
    {
        if (Gate::denies('orcamentos')) {
            return view('inicio', ['aguardando' => collect(), 'rascunhos' => collect()]);
        }
        $orcamentos->expirarVencidos(); // RN17

        $aguardando = Orcamento::with('cliente')->where('situacao', Orcamento::ENVIADO)
            ->orderBy('valido_ate')->limit(10)->get();
        $rascunhos = Orcamento::with('cliente')->where('situacao', Orcamento::RASCUNHO)
            ->orderByDesc('atualizado_em')->limit(10)->get();

        return view('inicio', compact('aguardando', 'rascunhos'));
    }
}
