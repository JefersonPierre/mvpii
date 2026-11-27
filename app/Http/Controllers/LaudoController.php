<?php

namespace App\Http\Controllers;

use App\Models\Amostra;
use App\Models\Configuracao;
use App\Models\Laudo;
use App\Models\RegistroAuditoria;
use App\Services\EmissaoLaudos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// MOD13 – UC26 Emitir e enviar (RF52, RF53), UC27 Retificar (RF54) e UC28 Consultar laudos e não conformidades (RF57).
class LaudoController extends Controller
{
    public function __construct(private readonly EmissaoLaudos $laudos) {}

    public function index(Request $request): View
    {
        $aba = $request->query('aba') === 'nao-conformes' ? 'nao-conformes' : 'laudos';
        $filtros = [
            'numero' => mb_strtoupper(trim((string) $request->query('numero'))),
            'cliente' => trim((string) $request->query('cliente')),
            'ponto' => trim((string) $request->query('ponto')),
            'de' => $request->date('de')?->toDateString(),
            'ate' => $request->date('ate')?->toDateString(),
            'conclusao' => array_key_exists($request->query('conclusao'), Amostra::CONFORMIDADES) ? $request->query('conclusao') : '',
            'situacao' => array_key_exists($request->query('situacao'), Laudo::SITUACOES) ? $request->query('situacao') : '',
        ];
        $termo = fn (string $t) => '%'.mb_strtolower($t).'%';
        $doCliente = fn ($q, $c) => $q->whereHas('coleta.orcamento.cliente', fn ($q) => $q->whereRaw('LOWER(nome) LIKE ?', [$termo($c)]));
        $doPonto = fn ($q, $p) => $q->whereHas('pontoColeta', fn ($q) => $q->whereRaw('LOWER(identificacao) LIKE ?', [$termo($p)]));

        if ($aba === 'nao-conformes') {
            // UC28 2a: amostras com algum parâmetro não conforme, em qualquer etapa depois do lançamento.
            $itens = Amostra::with(['coleta.orcamento.cliente', 'pontoColeta', 'laudoVigente',
                'resultados' => fn ($q) => $q->where('conformidade', 'NAO_CONFORME')->with('parametro')])
                ->where('conformidade', Amostra::NAO_CONFORME)
                ->when($filtros['cliente'], $doCliente)->when($filtros['ponto'], $doPonto)
                ->when($filtros['de'], fn ($q, $d) => $q->whereHas('coleta', fn ($q) => $q->whereDate('data', '>=', $d)))
                ->when($filtros['ate'], fn ($q, $d) => $q->whereHas('coleta', fn ($q) => $q->whereDate('data', '<=', $d)))
                ->orderByDesc('ano')->orderByDesc('sequencia')->paginate(25)->withQueryString();
        } else {
            $itens = Laudo::with(['amostra.coleta.orcamento.cliente', 'amostra.pontoColeta'])
                ->when($filtros['numero'], function ($q, $n) {
                    preg_match('/(\d{4})\D*(\d+)/', $n, $m);
                    $m ? $q->where(['ano' => (int) $m[1], 'sequencia' => (int) $m[2]]) : $q->whereRaw('1 = 0');
                })
                ->when($filtros['cliente'], fn ($q, $c) => $q->whereHas('amostra', fn ($q) => $doCliente($q, $c)))
                ->when($filtros['ponto'], fn ($q, $p) => $q->whereHas('amostra', fn ($q) => $doPonto($q, $p)))
                ->when($filtros['de'], fn ($q, $d) => $q->whereDate('emitido_em', '>=', $d))
                ->when($filtros['ate'], fn ($q, $d) => $q->whereDate('emitido_em', '<=', $d))
                ->when($filtros['conclusao'], fn ($q, $c) => $q->where('conclusao', $c))
                ->when($filtros['situacao'], fn ($q, $s) => $q->where('situacao', $s))
                ->orderByDesc('emitido_em')->orderByDesc('id')->paginate(25)->withQueryString();
        }

        return view('laudos.index', compact('aba', 'filtros', 'itens'));
    }

    /** UC26 1: amostras Liberadas, agrupadas por coleta. */
    public function aEmitir(): View
    {
        $amostras = Amostra::with(['coleta.orcamento.cliente.contatoPrincipal', 'pontoColeta', 'laudoVigente'])
            ->where('situacao', Amostra::LIBERADA)->orderBy('coleta_id')->orderBy('sequencia')->get();

        return view('laudos.emitir', [
            'porColeta' => $amostras->groupBy('coleta_id'),
            'configurado' => Configuracao::atual()->laudoConfigurado(),
        ]);
    }

    /** UC26 3: pré-visualização em PDF, sem gravar nada. */
    public function previa(Amostra $amostra): Response
    {
        abort_unless(in_array($amostra->situacao, [Amostra::LIBERADA, Amostra::EM_REVISAO], true), 404);

        return response($this->laudos->previa($amostra), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="previa-'.$amostra->codigo.'.pdf"',
        ]);
    }

    /** UC26 4: emitir e enviar, ou só emitir (4a). */
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(['amostras' => ['required', 'array'], 'amostras.*' => ['integer']],
            ['amostras.required' => 'Selecione ao menos uma amostra.']);
        $emitidos = $this->laudos->emitir(Amostra::whereKey($dados['amostras'])->get());
        $avisos = $request->input('acao') === 'enviar' && Gate::allows('laudos.enviar') ? $this->laudos->enviar($emitidos) : [];

        $mensagem = ($emitidos->count() === 1 ? 'Laudo '.$emitidos->first()->numeroComRevisao().' emitido' : $emitidos->count().' laudos emitidos')
            .($request->input('acao') === 'enviar' ? (count($avisos) ? '.' : ' e enviados ao cliente.') : '. Envie ao cliente pelo laudo.');
        $resposta = $emitidos->count() === 1 ? redirect()->route('laudos.show', $emitidos->first()) : redirect()->route('laudos.index');

        return $resposta->with('sucesso', $mensagem)->with('aviso', implode(' ', $avisos) ?: null);
    }

    public function show(Laudo $laudo): View
    {
        $laudo->load(['amostra.coleta.orcamento.cliente.contatoPrincipal', 'amostra.pontoColeta', 'emitidoPor', 'enviadoPor',
            'substitui', 'substituidoPor', 'amostra.laudos']);
        $historico = RegistroAuditoria::with('responsavel')
            ->where('entidade', EmissaoLaudos::ENTIDADE)->whereIn('registro_id', $laudo->amostra->laudos->modelKeys())
            ->orderByDesc('data_hora')->orderByDesc('id')->paginate(30);

        return view('laudos.show', compact('laudo', 'historico'));
    }

    /** RNF17: o PDF guardado na emissão, sem gerar de novo. */
    public function pdf(Laudo $laudo): Response
    {
        abort_unless(Storage::disk('local')->exists($laudo->pdf_caminho), 404);

        return response(Storage::disk('local')->get($laudo->pdf_caminho), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$laudo->nomeArquivo().'"',
        ]);
    }

    /** UC28 4 / UC26 4a: envio ou reenvio ao contato principal. */
    public function enviar(Laudo $laudo): RedirectResponse
    {
        if (! $laudo->vigente()) {
            return back()->with('erro', 'O laudo Substituído não é enviado: envie o laudo vigente.');
        }
        $avisos = $this->laudos->enviar(collect([$laudo]));

        return back()->with($avisos ? 'erro' : 'sucesso', $avisos ? implode(' ', $avisos) : 'Laudo enviado para '.$laudo->fresh()->envio_email.'.');
    }

    /** UC27: retificação com motivo (RN55). */
    public function retificar(Request $request, Laudo $laudo): RedirectResponse
    {
        $dados = $request->validate(['motivo_retificacao' => ['required', 'string', 'max:255'], 'reabrir_resultados' => ['boolean']],
            ['motivo_retificacao.required' => 'Informe o motivo da retificação.']);
        $reabrir = $request->boolean('reabrir_resultados', true);
        $this->laudos->retificar($laudo, $dados['motivo_retificacao'], $reabrir);

        return redirect()->route('laudos.show', $laudo)->with('sucesso', $reabrir
            ? 'Retificação iniciada: os resultados foram reabertos para correção e nova liberação. Depois, emita o novo laudo.'
            : 'Retificação iniciada: a amostra está pronta para a emissão do novo laudo em Laudos > A emitir.');
    }
}
