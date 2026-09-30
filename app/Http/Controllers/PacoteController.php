<?php

namespace App\Http\Controllers;

use App\Http\Requests\PacoteRequest;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\TipoAmostra;
use App\Services\CadastroComHistorico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC05 1b – Cadastrar pacotes de análise (RF14, RN04, RN05, RN08)
class PacoteController extends Controller
{
    private const ENTIDADE = 'PACOTE';

    private const CAMPOS_AUDITADOS = ['nome', 'tipo_amostra', 'parametros', 'preco', 'ativo'];

    public function __construct(private readonly CadastroComHistorico $cadastro) {}

    public function index(Request $request): View
    {
        $tipoAmostra = (int) $request->query('tipo_amostra') ?: null;
        $pacotes = Pacote::with(['tipoAmostra', 'parametros'])
            ->when($tipoAmostra, fn ($q, $t) => $q->where('tipo_amostra_id', $t))
            ->orderByDesc('ativo')->orderByNome()->get();
        $tipos = TipoAmostra::query()->orderByNome()->get();

        return view('catalogo.pacotes.index', compact('pacotes', 'tipos', 'tipoAmostra'));
    }

    public function create(): View
    {
        return $this->formulario(new Pacote);
    }

    public function store(PacoteRequest $request): RedirectResponse
    {
        $this->salvar(new Pacote, $request->validated());

        return redirect()->route('catalogo.pacotes.index')->with('sucesso', 'Pacote cadastrado.');
    }

    public function edit(Pacote $pacote): View
    {
        return $this->formulario($pacote);
    }

    public function update(PacoteRequest $request, Pacote $pacote): RedirectResponse
    {
        $this->salvar($pacote, $request->validated());

        return redirect()->route('catalogo.pacotes.index')->with('sucesso', 'Pacote atualizado.');
    }

    public function situacao(Request $request, Pacote $pacote): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        $this->cadastro->alterarSituacao($pacote, $ativo, self::ENTIDADE, self::CAMPOS_AUDITADOS, $this->valores(...));

        return back()->with('sucesso', $ativo ? 'Pacote reativado.' : 'Pacote inativado.');
    }

    public function historico(Pacote $pacote): View
    {
        return view('catalogo.historico', [
            'titulo' => $pacote->nome,
            'voltar' => [route('catalogo.pacotes.index'), 'Pacotes'],
            'registros' => $this->cadastro->historico(self::ENTIDADE, $pacote->id),
            'campos' => ['nome' => 'Nome', 'tipo_amostra' => 'Tipo de amostra', 'parametros' => 'Parâmetros', 'preco' => 'Preço', 'ativo' => 'Situação'],
        ]);
    }

    private function salvar(Pacote $pacote, array $dados): void
    {
        $this->cadastro->salvar(
            $pacote,
            collect($dados)->only(['nome', 'tipo_amostra_id', 'preco'])->all(),
            self::ENTIDADE,
            self::CAMPOS_AUDITADOS,
            fn (Pacote $p) => $p->parametros()->sync($dados['parametros']),
            $this->valores(...),
        );
    }

    /** No histórico, o tipo de amostra e os parâmetros aparecem pelo nome. */
    private function valores(Pacote $pacote): array
    {
        return [
            ...$pacote->only(['nome', 'preco', 'ativo']),
            'tipo_amostra' => $pacote->tipoAmostra()->value('nome'),
            'parametros' => $pacote->parametros()->pluck('nome')->ordenarPorNome()->implode(', '),
        ];
    }

    private function formulario(Pacote $pacote): View
    {
        $atuais = $pacote->exists ? $pacote->parametros()->pluck('parametros.id')->all() : [];
        // Ativos, e na edição também os que já estão no pacote (mesmo que inativados depois).
        $parametros = Parametro::where('ativo', true)->orWhereIn('id', $atuais)->orderByNome()->get()->groupBy('categoria');
        $tipos = TipoAmostra::where('ativo', true)->orWhere('id', $pacote->tipo_amostra_id)->orderByNome()->get();

        return view('catalogo.pacotes.form', compact('pacote', 'parametros', 'tipos', 'atuais'));
    }
}
