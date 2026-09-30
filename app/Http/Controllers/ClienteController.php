<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Models\RegistroAuditoria;
use App\Services\CadastroClientes;
use App\Services\CadastroPontos;
use App\Support\Documento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC03 – Cadastrar cliente (RF04–RF07) e UC07 – Consultar clientes e histórico (RF08, RF23, RF24)
class ClienteController extends Controller
{
    public function __construct(private readonly CadastroClientes $cadastro) {}

    /** RF08: pesquisa por nome, CPF/CNPJ, cidade e situação. */
    public function index(Request $request): View
    {
        $filtros = [
            'busca' => trim((string) $request->query('busca')),
            'cidade' => trim((string) $request->query('cidade')),
            'situacao' => in_array($request->query('situacao'), ['ativos', 'inativos', 'todos'], true)
                ? $request->query('situacao') : 'ativos',
        ];

        $clientes = Cliente::query()
            ->with('contatoPrincipal')
            ->when($filtros['situacao'] !== 'todos', fn ($q) => $q->where('ativo', $filtros['situacao'] === 'ativos'))
            ->when($filtros['cidade'] !== '', fn ($q) => $q->where('cidade', $filtros['cidade']))
            ->when($filtros['busca'] !== '', function ($q) use ($filtros) {
                $termo = '%'.mb_strtolower($filtros['busca']).'%';
                $documento = Documento::limpar($filtros['busca']);
                $q->where(function ($q) use ($termo, $documento) {
                    $q->whereRaw('LOWER(nome) LIKE ?', [$termo])->orWhereRaw('LOWER(nome_fantasia) LIKE ?', [$termo]);
                    if (strlen($documento) >= 3) {
                        $q->orWhere('documento', 'like', "%{$documento}%");
                    }
                });
            })
            ->orderByNome()
            ->paginate(20)
            ->withQueryString();

        $cidades = Cliente::whereNotNull('cidade')->distinct()->orderBy('cidade')->pluck('cidade');

        return view('clientes.index', compact('clientes', 'filtros', 'cidades'));
    }

    public function create(Request $request): View
    {
        $cliente = new Cliente([
            'tipo_pessoa' => 'J',
            'interessado' => $request->query('cadastro') === 'interessado',
        ]);

        return view('clientes.form', compact('cliente'));
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $cliente = $this->cadastro->salvar(new Cliente, $request->validated());

        return redirect()->route('clientes.show', $cliente)
            ->with('sucesso', $cliente->interessado ? 'Interessado cadastrado.' : 'Cliente cadastrado.');
    }

    /** RF23: ficha do cliente com abas (dados, contatos, pontos de coleta, orçamentos e histórico). */
    public function show(Request $request, Cliente $cliente): View
    {
        $aba = in_array($request->query('aba'), ['dados', 'contatos', 'pontos', 'orcamentos', 'historico'], true)
            ? $request->query('aba') : 'dados';

        $cliente->load(['contatos', 'pontosColeta.tipoAmostra']);
        $historico = $aba === 'historico' ? $this->historico($cliente) : null;

        return view('clientes.show', compact('cliente', 'aba', 'historico'));
    }

    /** RF24: histórico do cliente e dos seus pontos de coleta, do mais recente para o mais antigo. */
    private function historico(Cliente $cliente): LengthAwarePaginator
    {
        $pontos = $cliente->pontosColeta->pluck('identificacao', 'id');

        $registros = RegistroAuditoria::with('responsavel')
            ->where(fn ($q) => $q->where(['entidade' => CadastroClientes::ENTIDADE, 'registro_id' => $cliente->id]))
            ->orWhere(fn ($q) => $q->where('entidade', CadastroPontos::ENTIDADE)->whereIn('registro_id', $pontos->keys()))
            ->orderByDesc('data_hora')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        // Nas linhas de ponto de coleta, o campo mostra também de qual ponto se trata.
        $registros->getCollection()->each(function (RegistroAuditoria $r) use ($pontos) {
            if ($r->entidade === CadastroPontos::ENTIDADE) {
                $r->setAttribute('prefixo_campo', 'Ponto '.$pontos[$r->registro_id]);
            }
        });

        return $registros;
    }

    public function edit(Cliente $cliente): View
    {
        $cliente->load('contatos');

        return view('clientes.form', compact('cliente'));
    }

    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->cadastro->salvar($cliente, $request->validated());

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Cadastro atualizado.');
    }

    /** RF07 / RN04: inativar ou reativar. */
    public function situacao(Request $request, Cliente $cliente): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        $this->cadastro->alterarSituacao($cliente, $ativo);

        return redirect()->route('clientes.show', $cliente)
            ->with('sucesso', $ativo ? 'Cliente reativado.' : 'Cliente inativado.');
    }
}
