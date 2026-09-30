<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoAmostraRequest;
use App\Models\TipoAmostra;
use App\Services\CadastroComHistorico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC05 1a – Cadastrar tipos de amostra (RF12, RN04, RN05, RN08)
class TipoAmostraController extends Controller
{
    private const ENTIDADE = 'TIPO_AMOSTRA';

    private const CAMPOS_AUDITADOS = ['nome', 'descricao', 'ativo'];

    public function __construct(private readonly CadastroComHistorico $cadastro) {}

    public function index(): View
    {
        $tipos = TipoAmostra::withCount(['pontosColeta' => fn ($q) => $q->where('ativo', true)])
            ->orderByDesc('ativo')->orderByNome()->get();

        return view('catalogo.tipos-amostra.index', compact('tipos'));
    }

    public function create(): View
    {
        return view('catalogo.tipos-amostra.form', ['tipo' => new TipoAmostra]);
    }

    public function store(TipoAmostraRequest $request): RedirectResponse
    {
        $this->cadastro->salvar(new TipoAmostra, $request->validated(), self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return redirect()->route('catalogo.tipos-amostra.index')->with('sucesso', 'Tipo de amostra cadastrado.');
    }

    public function edit(TipoAmostra $tipo): View
    {
        return view('catalogo.tipos-amostra.form', compact('tipo'));
    }

    public function update(TipoAmostraRequest $request, TipoAmostra $tipo): RedirectResponse
    {
        $this->cadastro->salvar($tipo, $request->validated(), self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return redirect()->route('catalogo.tipos-amostra.index')->with('sucesso', 'Tipo de amostra atualizado.');
    }

    /** RN04: inativado, o tipo deixa de aparecer em novos cadastros; os pontos que já o usam continuam como estão. */
    public function situacao(Request $request, TipoAmostra $tipo): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        $this->cadastro->alterarSituacao($tipo, $ativo, self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return back()->with('sucesso', $ativo ? 'Tipo de amostra reativado.' : 'Tipo de amostra inativado.');
    }

    public function historico(TipoAmostra $tipo): View
    {
        $registros = $this->cadastro->historico(self::ENTIDADE, $tipo->id);

        return view('catalogo.tipos-amostra.historico', compact('tipo', 'registros'));
    }
}
