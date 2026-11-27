<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgendamentoRequest;
use App\Mail\ColetaMail;
use App\Models\Coleta;
use App\Models\Orcamento;
use App\Services\AgendamentoColetas;
use App\Services\AvisosColeta;
use App\Services\CadastroComHistorico;
use App\Services\Rastreabilidade;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// UC14 – Registrar agendamento de coleta (RF27–RF30): agendar, reagendar e cancelar; UC15 – detalhes e ficha (RF32).
class ColetaController extends Controller
{
    public function __construct(
        private readonly AgendamentoColetas $agendamento,
        private readonly AvisosColeta $avisos,
    ) {}

    public function create(Orcamento $orcamento): View|RedirectResponse
    {
        if (! $orcamento->permiteAgendarColeta()) {
            return redirect()->route('orcamentos.show', $orcamento)->with('erro', 'Só é possível agendar coleta de orçamento Aprovado.');
        }

        return $this->formulario($orcamento, new Coleta);
    }

    public function store(AgendamentoRequest $request, Orcamento $orcamento): RedirectResponse
    {
        if ($conflito = $this->conflito($request)) {
            return $conflito;
        }
        $coleta = $this->agendamento->agendar($orcamento, $request->validated());

        return $this->depoisDoAviso($coleta, ColetaMail::CONFIRMACAO,
            'Coleta agendada: '.$coleta->amostras()->count().' amostra(s), códigos gerados.');
    }

    public function show(Coleta $coleta, CadastroComHistorico $historico): View
    {
        Gate::authorize('view', $coleta);
        $coleta->load(['orcamento.cliente.contatoPrincipal', 'coletor', 'criadoPor', 'registradaPor', 'amostras.pontoColeta',
            'amostras.itens']);
        $registros = Gate::allows('historico') ? $historico->historico(Rastreabilidade::COLETA, $coleta->id) : null;
        $conflitos = $coleta->sincronizacoes()->with('usuario')->whereNotNull('motivo_conflito')->get();

        return view('coletas.show', compact('coleta', 'registros', 'conflitos'));
    }

    /** RF32: ficha de coleta em PDF para o coletor levar a campo (uma página por ponto de coleta). */
    public function ficha(Coleta $coleta): Response
    {
        Gate::authorize('view', $coleta);
        $coleta->load(['orcamento.cliente.contatoPrincipal', 'orcamento.tipoAmostra', 'coletor', 'amostras.pontoColeta', 'amostras.itens']);

        $pdf = Pdf::loadView('coletas.ficha', ['coleta' => $coleta, 'laboratorio' => config('laboratorio.dados')])
            ->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ficha-coleta-'.$coleta->data->format('Y-m-d').'-'.$coleta->id.'.pdf"',
        ]);
    }

    /** UC14 1a: reagendar (nova data, período ou coletor e motivo). */
    public function edit(Coleta $coleta): View|RedirectResponse
    {
        if (! $coleta->podeReagendar()) {
            return redirect()->route('coletas.show', $coleta)->with('erro', 'Só a coleta Agendada ou Não realizada pode ser reagendada.');
        }

        return $this->formulario($coleta->orcamento, $coleta);
    }

    public function update(AgendamentoRequest $request, Coleta $coleta): RedirectResponse
    {
        if ($conflito = $this->conflito($request, $coleta)) {
            return $conflito;
        }
        $this->agendamento->reagendar($coleta, $request->validated());

        return $this->depoisDoAviso($coleta, ColetaMail::REAGENDAMENTO, 'Coleta reagendada. Os códigos das amostras foram mantidos.');
    }

    /** UC14 1b: cancelar com motivo; o saldo do orçamento é devolvido (RN28). */
    public function cancelar(Request $request, Coleta $coleta): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Informe o motivo do cancelamento.']);

        try {
            $this->agendamento->cancelar($coleta, $dados['motivo']);
        } catch (ValidationException $erro) {
            return back()->with('erro', $erro->getMessage());
        }

        return $this->depoisDoAviso($coleta, ColetaMail::CANCELAMENTO, 'Coleta cancelada. O saldo de amostras voltou ao orçamento.');
    }

    private function formulario(Orcamento $orcamento, Coleta $coleta): View
    {
        $orcamento->load(['cliente.contatoPrincipal', 'tipoAmostra']);

        return view('coletas.form', [
            'orcamento' => $orcamento,
            'coleta' => $coleta,
            'saldo' => $this->agendamento->saldo($orcamento),
            'pontos' => $this->agendamento->pontosPermitidos($orcamento),
            'coletores' => $this->agendamento->coletores($coleta->coletor_id),
            'ocupacao' => $this->ocupacao($coleta),
        ]);
    }

    /**
     * Coletas agendadas por coletor, dia e período nos próximos meses, para o alerta de coletor ocupado na tela (RN29).
     *
     * @return array<string, int> "coletor|AAAA-MM-DD|PERIODO" => quantidade
     */
    private function ocupacao(Coleta $coleta): array
    {
        return Coleta::where('situacao', Coleta::AGENDADA)
            ->whereDate('data', '>=', today())->whereDate('data', '<=', today()->addMonths(3))
            ->when($coleta->exists, fn ($q) => $q->whereKeyNot($coleta->id))
            ->get(['coletor_id', 'data', 'periodo'])
            ->countBy(fn (Coleta $c) => "{$c->coletor_id}|{$c->data->toDateString()}|{$c->periodo}")
            ->all();
    }

    /** RN29 / UC14 3a: coletor com outra coleta no mesmo dia e período: alerta e pede confirmação. */
    private function conflito(AgendamentoRequest $request, ?Coleta $coleta = null): ?RedirectResponse
    {
        if ($request->boolean('confirmar_conflito')) {
            return null;
        }
        $dados = $request->validated();
        $quantidade = $this->agendamento->coletasDoColetorNoPeriodo((int) $dados['coletor_id'], $dados['data'], $dados['periodo'], $coleta?->id);
        if ($quantidade === 0) {
            return null;
        }

        return back()->withInput()->with('conflito', ($quantidade === 1 ? 'O coletor já tem 1 coleta' : "O coletor já tem {$quantidade} coletas")
            .' agendada(s) neste dia e período. Confirme para agendar mesmo assim ou altere a data, o período ou o coletor.');
    }

    /** RF30 / RN37: avisa o cliente e mostra o resultado do aviso junto com a mensagem de sucesso. */
    private function depoisDoAviso(Coleta $coleta, string $tipo, string $sucesso): RedirectResponse
    {
        $problema = $this->avisos->avisar($coleta, $tipo);
        $resposta = redirect()->route('coletas.show', $coleta);

        return $problema
            ? $resposta->with('sucesso', $sucesso)->with('erro', $problema)
            : $resposta->with('sucesso', $sucesso.' O cliente foi avisado por e-mail ('.$this->avisos->destinatario($coleta).').');
    }
}
