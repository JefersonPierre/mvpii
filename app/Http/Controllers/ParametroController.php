<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParametroRequest;
use App\Models\Parametro;
use App\Services\CadastroComHistorico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC05 – Cadastrar parâmetros de análise (RF13, RN04, RN05, RN08)
class ParametroController extends Controller
{
    private const ENTIDADE = 'PARAMETRO';

    private const CAMPOS_AUDITADOS = ['nome', 'unidade', 'metodo', 'limite_quantificacao', 'categoria', 'preco', 'ativo'];

    public function __construct(private readonly CadastroComHistorico $cadastro) {}

    public function index(Request $request): View
    {
        $filtros = [
            'busca' => trim((string) $request->query('busca')),
            'categoria' => array_key_exists($request->query('categoria'), Parametro::CATEGORIAS) ? $request->query('categoria') : '',
            'situacao' => in_array($request->query('situacao'), ['ativos', 'inativos', 'todos'], true) ? $request->query('situacao') : 'ativos',
        ];

        $parametros = Parametro::query()
            ->when($filtros['situacao'] !== 'todos', fn ($q) => $q->where('ativo', $filtros['situacao'] === 'ativos'))
            ->when($filtros['categoria'], fn ($q, $c) => $q->where('categoria', $c))
            ->when($filtros['busca'], fn ($q, $b) => $q->whereRaw('LOWER(nome) LIKE ?', ['%'.mb_strtolower($b).'%']))
            ->orderByNome()->get();

        return view('catalogo.parametros.index', compact('parametros', 'filtros'));
    }

    public function create(): View
    {
        return view('catalogo.parametros.form', ['parametro' => new Parametro(['categoria' => 'FISICO_QUIMICA'])]);
    }

    public function store(ParametroRequest $request): RedirectResponse
    {
        $this->cadastro->salvar(new Parametro, $request->validated(), self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return redirect()->route('catalogo.parametros.index')->with('sucesso', 'Parâmetro cadastrado.');
    }

    public function edit(Parametro $parametro): View
    {
        return view('catalogo.parametros.form', compact('parametro'));
    }

    /** RN15: mudar o preço aqui não altera orçamentos já emitidos (eles guardam uma cópia do preço). */
    public function update(ParametroRequest $request, Parametro $parametro): RedirectResponse
    {
        $this->cadastro->salvar($parametro, $request->validated(), self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return redirect()->route('catalogo.parametros.index')->with('sucesso', 'Parâmetro atualizado.');
    }

    /** RN04: inativado, o parâmetro não entra em novos pacotes, limites e orçamentos. */
    public function situacao(Request $request, Parametro $parametro): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        $this->cadastro->alterarSituacao($parametro, $ativo, self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return back()->with('sucesso', $ativo ? 'Parâmetro reativado.' : 'Parâmetro inativado.');
    }

    public function historico(Parametro $parametro): View
    {
        $registros = $this->cadastro->historico(self::ENTIDADE, $parametro->id);

        return view('catalogo.historico', [
            'titulo' => $parametro->nome,
            'voltar' => [route('catalogo.parametros.index'), 'Parâmetros'],
            'registros' => $registros,
            'campos' => ['nome' => 'Nome', 'unidade' => 'Unidade', 'metodo' => 'Método', 'limite_quantificacao' => 'Limite de quantificação',
                'categoria' => 'Categoria', 'preco' => 'Preço', 'ativo' => 'Situação'],
        ]);
    }
}
