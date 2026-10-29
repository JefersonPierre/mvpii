<?php

namespace App\Http\Controllers;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Orcamento;
use App\Models\Sincronizacao;
use App\Services\CadastroOrcamentos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Tela inicial: atalhos e pendências de cada perfil (o atendimento começa pelo orçamento, seção 1 da documentação).
class InicioController extends Controller
{
    public function __invoke(Request $request, CadastroOrcamentos $orcamentos): View
    {
        $usuario = $request->user();
        $aguardando = $rascunhos = $proximas = $naoRealizadas = $aReceber = $conflitos = collect();
        $pendencias = []; // Entregável 3: resultados, revisão e laudos

        if (Gate::allows('orcamentos')) {
            $orcamentos->expirarVencidos(); // RN17
            $aguardando = Orcamento::with('cliente')->where('situacao', Orcamento::ENVIADO)
                ->orderBy('valido_ate')->limit(10)->get();
            $rascunhos = Orcamento::with('cliente')->where('situacao', Orcamento::RASCUNHO)
                ->orderByDesc('atualizado_em')->limit(10)->get();
        }
        if (Gate::allows('coletas.agenda')) {
            // RN36: o coletor vê só as próprias.
            $proximas = Coleta::with(['orcamento.cliente', 'coletor'])->withCount('amostras')
                ->where('situacao', Coleta::AGENDADA)->whereDate('data', '>=', today())
                ->when(! $usuario->veTodasAsColetas(), fn ($q) => $q->where('coletor_id', $usuario->id))
                ->orderBy('data')->orderBy('periodo')->limit(10)->get();
        }
        if (Gate::allows('coletas.agendar')) {
            $naoRealizadas = Coleta::with('orcamento.cliente')->where('situacao', Coleta::NAO_REALIZADA)
                ->orderBy('data')->limit(10)->get();
        }
        if (Gate::allows('amostras.receber')) {
            $aReceber = Amostra::with(['coleta.orcamento.cliente', 'pontoColeta'])->where('situacao', Amostra::COLETADA)
                ->orderBy('sequencia')->limit(10)->get();
        }

        if (Gate::allows('coletas.conflitos')) {
            // RN42: a Recepção é avisada dos conflitos de sincronização.
            $conflitos = Sincronizacao::with(['coleta.orcamento.cliente', 'usuario'])->where('situacao', Sincronizacao::CONFLITO)
                ->orderBy('recebida_em')->limit(10)->get();
        }
        if (Gate::allows('resultados.lancar')) {
            $pendencias[] = ['Resultados a lançar', Amostra::whereIn('situacao', Amostra::RECEBEM_RESULTADOS)->count(), route('resultados.index')];
        }
        if (Gate::allows('resultados.liberar')) {
            $pendencias[] = ['Resultados a revisar', Amostra::where('situacao', Amostra::EM_REVISAO)
                ->whereDoesntHave('resultados', fn ($q) => $q->where('lancado_por_id', $usuario->id))->count(), route('resultados.index', ['aba' => 'revisar'])];
        }
        if (Gate::allows('laudos.emitir')) {
            $pendencias[] = ['Laudos a emitir', Amostra::where('situacao', Amostra::LIBERADA)->count(), route('laudos.emitir')];
        }

        return view('inicio', compact('aguardando', 'rascunhos', 'proximas', 'naoRealizadas', 'aReceber', 'conflitos', 'pendencias'));
    }
}
