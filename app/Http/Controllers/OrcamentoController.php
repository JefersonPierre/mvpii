<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrcamentoRequest;
use App\Models\Cliente;
use App\Models\Configuracao;
use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\TipoAmostra;
use App\Services\CadastroComHistorico;
use App\Services\CadastroOrcamentos;
use App\Support\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC08 – Elaborar orçamento (RF17, RF20) e UC11 – Consultar orçamentos (RF21)
class OrcamentoController extends Controller
{
    public function __construct(private readonly CadastroOrcamentos $cadastro) {}

    /** RF21: pesquisa por número, cliente, período e situação; sem filtros, os mais recentes. */
    public function index(Request $request): View
    {
        $this->cadastro->expirarVencidos(); // RN17

        $filtros = [
            'numero' => trim((string) $request->query('numero')),
            'cliente' => trim((string) $request->query('cliente')),
            'de' => $request->date('de')?->toDateString(),
            'ate' => $request->date('ate')?->toDateString(),
            'situacao' => array_key_exists($request->query('situacao'), Orcamento::SITUACOES) ? $request->query('situacao') : '',
        ];

        $orcamentos = Orcamento::with('cliente')
            ->when($filtros['numero'], function ($q, $numero) {
                // Aceita "ORC-2026-0042", "2026-42" ou só "42".
                preg_match_all('/\d+/', $numero, $partes);
                $partes = array_map('intval', $partes[0]);
                $sequencia = array_pop($partes);
                $ano = array_pop($partes);
                $q->where('sequencia', $sequencia)->when($ano, fn ($q) => $q->where('ano', $ano));
            })
            ->when($filtros['cliente'], fn ($q, $c) => $q->whereHas('cliente', fn ($q) => $q->whereRaw('LOWER(nome) LIKE ?', ['%'.mb_strtolower($c).'%'])))
            ->when($filtros['de'], fn ($q, $d) => $q->whereDate('criado_em', '>=', $d))
            ->when($filtros['ate'], fn ($q, $d) => $q->whereDate('criado_em', '<=', $d))
            ->when($filtros['situacao'], fn ($q, $s) => $q->where('situacao', $s))
            ->orderByDesc('ano')->orderByDesc('sequencia')->orderByDesc('revisao')
            ->paginate(25)->withQueryString();

        return view('orcamentos.index', compact('orcamentos', 'filtros'));
    }

    /** ?cliente={id}: vem do cadastro rápido de interessado (UC08 1a) ou da ficha do cliente. */
    public function create(Request $request): View
    {
        $config = Configuracao::atual();
        $orcamento = new Orcamento([
            'cliente_id' => Cliente::where('ativo', true)->find($request->query('cliente'))?->id,
            'taxa_coleta' => $config->taxa_coleta,
            'desconto_percentual' => 0,
            'validade_dias' => $config->validade_dias,
            'condicoes_pagamento' => $config->condicoes_comerciais,
        ]);
        if ($orcamento->cliente_id) {
            $orcamento->contato_id = $orcamento->cliente->contatoPrincipal?->id;
        }

        return $this->formulario($orcamento);
    }

    public function store(OrcamentoRequest $request): RedirectResponse
    {
        $orcamento = $this->cadastro->salvarRascunho(new Orcamento, $request->validated());

        return redirect()->route('orcamentos.show', $orcamento)
            ->with('sucesso', "Orçamento {$orcamento->numero()} salvo como rascunho.");
    }

    public function show(Orcamento $orcamento, CadastroComHistorico $historico): View
    {
        $this->cadastro->expirarVencidos(); // RN17
        $orcamento->refresh()->load(['cliente', 'contato', 'tipoAmostra', 'itens', 'criadoPor']);
        $revisoes = Orcamento::where(['ano' => $orcamento->ano, 'sequencia' => $orcamento->sequencia])->orderByDesc('revisao')->get();
        $registros = $historico->historico(CadastroOrcamentos::ENTIDADE, $orcamento->id);

        return view('orcamentos.show', compact('orcamento', 'revisoes', 'registros'));
    }

    public function edit(Orcamento $orcamento): View|RedirectResponse
    {
        return $this->somenteRascunho($orcamento) ?? $this->formulario($orcamento->load('itens'));
    }

    public function update(OrcamentoRequest $request, Orcamento $orcamento): RedirectResponse
    {
        if ($bloqueio = $this->somenteRascunho($orcamento)) {
            return $bloqueio;
        }
        $this->cadastro->salvarRascunho($orcamento, $request->validated());

        return redirect()->route('orcamentos.show', $orcamento)->with('sucesso', 'Rascunho atualizado.');
    }

    /** RF20 / UC08 1b */
    public function duplicar(Orcamento $orcamento): RedirectResponse
    {
        [$copia, $ignorados] = $this->cadastro->duplicar($orcamento);
        $resposta = redirect()->route('orcamentos.edit', $copia)
            ->with('sucesso', "Orçamento {$copia->numero()} criado a partir de {$orcamento->numeroComRevisao()}, com os preços atuais do catálogo.");

        return $ignorados
            ? $resposta->with('erro', 'Itens inativos no catálogo ficaram de fora: '.implode(', ', $ignorados).'.')
            : $resposta;
    }

    /** RF20 / RN16 / UC09 1a */
    public function novaRevisao(Orcamento $orcamento): RedirectResponse
    {
        if (! $orcamento->permiteNovaRevisao()) {
            return back()->with('erro', 'Só é possível criar nova revisão de orçamento enviado, expirado ou recusado.');
        }
        $revisao = $this->cadastro->novaRevisao($orcamento);

        return redirect()->route('orcamentos.edit', $revisao)
            ->with('sucesso', "Revisão {$revisao->revisao} criada. A revisão {$orcamento->revisao} ficou como Substituída.");
    }

    /** Busca de clientes ativos para o formulário (nome ou CPF/CNPJ), com os contatos. */
    public function buscarClientes(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q'));
        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }
        $documento = Documento::limpar($termo);

        $clientes = Cliente::with('contatos')->where('ativo', true)
            ->where(function ($q) use ($termo, $documento) {
                $q->whereRaw('LOWER(nome) LIKE ?', ['%'.mb_strtolower($termo).'%']);
                if (strlen($documento) >= 3) {
                    $q->orWhere('documento', 'like', "%{$documento}%");
                }
            })
            ->orderByNome()->limit(10)->get();

        return response()->json($clientes->map(fn (Cliente $c) => $this->clienteJson($c)));
    }

    private function formulario(Orcamento $orcamento): View
    {
        $atuais = $orcamento->exists ? $orcamento->itens : collect();
        $tipos = TipoAmostra::where('ativo', true)->orWhere('id', $orcamento->tipo_amostra_id)->orderByNome()->get();
        $pacotes = Pacote::with('parametros')->where('ativo', true)
            ->orWhereIn('id', $atuais->pluck('pacote_id')->filter())->orderByNome()->get();
        $parametros = Parametro::where('ativo', true)
            ->orWhereIn('id', $atuais->pluck('parametro_id')->filter())->orderByNome()->get();
        // Após erro de validação, mostra o cliente que o usuário tinha escolhido.
        $cliente = Cliente::with('contatos')->find(old('cliente_id', $orcamento->cliente_id));

        return view('orcamentos.form', [
            'orcamento' => $orcamento,
            'tipos' => $tipos,
            'pacotes' => $pacotes,
            'parametros' => $parametros,
            'clienteJson' => $cliente ? $this->clienteJson($cliente) : null,
            'descontoMaximo' => Configuracao::atual()->desconto_maximo,
        ]);
    }

    private function clienteJson(Cliente $cliente): array
    {
        return [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'documento' => $cliente->documentoFormatado(),
            'interessado' => $cliente->interessado,
            'contatos' => $cliente->contatos->map(fn ($c) => [
                'id' => $c->id, 'nome' => $c->nome, 'email' => $c->email, 'principal' => $c->principal,
            ])->values(),
        ];
    }

    /** RN16: depois de enviado, o orçamento só muda por nova revisão. */
    private function somenteRascunho(Orcamento $orcamento): ?RedirectResponse
    {
        return $orcamento->editavel() ? null
            : redirect()->route('orcamentos.show', $orcamento)
                ->with('erro', 'Só o rascunho pode ser alterado. Para mudar um orçamento enviado, crie uma nova revisão.');
    }
}
