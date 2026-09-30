<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoAmostraRequest;
use App\Models\RegistroAuditoria;
use App\Models\TipoAmostra;
use App\Services\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// UC05 1a – Cadastrar tipos de amostra (RF12, RN04, RN05, RN08)
class TipoAmostraController extends Controller
{
    private const ENTIDADE = 'TIPO_AMOSTRA';

    private const CAMPOS_AUDITADOS = ['nome', 'descricao', 'ativo'];

    public function __construct(private readonly Auditoria $auditoria) {}

    public function index(): View
    {
        $tipos = TipoAmostra::withCount(['pontosColeta' => fn ($q) => $q->where('ativo', true)])
            ->get()->ordenarPorNome()->sortByDesc('ativo')->values();

        return view('catalogo.tipos-amostra.index', compact('tipos'));
    }

    public function create(): View
    {
        return view('catalogo.tipos-amostra.form', ['tipo' => new TipoAmostra]);
    }

    public function store(TipoAmostraRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $tipo = TipoAmostra::create($request->validated());
            $this->auditoria->registrar(self::ENTIDADE, $tipo->id, Auditoria::INCLUSAO, [], $tipo->only(self::CAMPOS_AUDITADOS), self::CAMPOS_AUDITADOS);
        });

        return redirect()->route('catalogo.tipos-amostra.index')->with('sucesso', 'Tipo de amostra cadastrado.');
    }

    public function edit(TipoAmostra $tipo): View
    {
        return view('catalogo.tipos-amostra.form', compact('tipo'));
    }

    public function update(TipoAmostraRequest $request, TipoAmostra $tipo): RedirectResponse
    {
        $this->gravar($tipo, $request->validated(), Auditoria::ALTERACAO);

        return redirect()->route('catalogo.tipos-amostra.index')->with('sucesso', 'Tipo de amostra atualizado.');
    }

    /** RN04: inativado, o tipo deixa de aparecer em novos cadastros; os pontos que já o usam continuam como estão. */
    public function situacao(Request $request, TipoAmostra $tipo): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        if ($tipo->ativo !== $ativo) {
            $this->gravar($tipo, ['ativo' => $ativo], $ativo ? Auditoria::REATIVACAO : Auditoria::INATIVACAO);
        }

        return back()->with('sucesso', $ativo ? 'Tipo de amostra reativado.' : 'Tipo de amostra inativado.');
    }

    public function historico(TipoAmostra $tipo): View
    {
        $registros = RegistroAuditoria::with('responsavel')
            ->where(['entidade' => self::ENTIDADE, 'registro_id' => $tipo->id])
            ->orderByDesc('data_hora')->orderByDesc('id')
            ->paginate(30);

        return view('catalogo.tipos-amostra.historico', compact('tipo', 'registros'));
    }

    private function gravar(TipoAmostra $tipo, array $dados, string $acao): void
    {
        DB::transaction(function () use ($tipo, $dados, $acao) {
            $antes = $tipo->only(self::CAMPOS_AUDITADOS);
            $tipo->update($dados);
            $this->auditoria->registrar(self::ENTIDADE, $tipo->id, $acao, $antes, $tipo->only(self::CAMPOS_AUDITADOS), self::CAMPOS_AUDITADOS);
        });
    }
}
