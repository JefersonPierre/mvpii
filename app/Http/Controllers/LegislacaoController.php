<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegislacaoRequest;
use App\Models\Legislacao;
use App\Models\TipoAmostra;
use App\Services\CadastroComHistorico;
use App\Services\CadastroLegislacoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC06 – Cadastrar legislação e limites (RF15, RF16, RN06, RN07, RN08)
class LegislacaoController extends Controller
{
    public function __construct(private readonly CadastroLegislacoes $cadastro) {}

    public function index(): View
    {
        $legislacoes = Legislacao::with('tiposAmostra')->withCount('limites')
            ->orderByRaw('fim_vigencia IS NOT NULL')->orderByDesc('inicio_vigencia')->get();

        return view('catalogo.legislacoes.index', compact('legislacoes'));
    }

    /** ?substitui={id}: nova legislação que substitui outra (UC06 1a). */
    public function create(Request $request): View
    {
        $substituida = $request->filled('substitui')
            ? Legislacao::with('tiposAmostra')->whereNull('substituida_por_id')->find($request->query('substitui'))
            : null;
        $legislacao = new Legislacao(['orgao_emissor' => $substituida?->orgao_emissor]);

        return $this->formulario($legislacao, $substituida);
    }

    public function store(LegislacaoRequest $request): RedirectResponse
    {
        $legislacao = $this->cadastro->salvar(new Legislacao, $request->validated());

        return redirect()->route('catalogo.legislacoes.show', $legislacao)->with('sucesso', 'Legislação cadastrada.');
    }

    public function show(Legislacao $legislacao): View
    {
        $legislacao->load(['tiposAmostra', 'substituidaPor']);
        // Por tipo de amostra e, dentro dele, por parâmetro (a ordenação do PHP é estável).
        $limites = $legislacao->limites()->with(['parametro', 'tipoAmostra'])->get()
            ->ordenarPorNome('parametro.nome')->ordenarPorNome('tipoAmostra.nome');
        $substituiu = Legislacao::where('substituida_por_id', $legislacao->id)->first();

        return view('catalogo.legislacoes.show', compact('legislacao', 'limites', 'substituiu'));
    }

    public function edit(Legislacao $legislacao): View
    {
        return $this->formulario($legislacao, null);
    }

    public function update(LegislacaoRequest $request, Legislacao $legislacao): RedirectResponse
    {
        $this->cadastro->salvar($legislacao, $request->validated());

        return redirect()->route('catalogo.legislacoes.show', $legislacao)->with('sucesso', 'Legislação atualizada.');
    }

    public function historico(Legislacao $legislacao, CadastroComHistorico $historico): View
    {
        return view('catalogo.historico', [
            'titulo' => $legislacao->nome,
            'voltar' => [route('catalogo.legislacoes.show', $legislacao), $legislacao->nome],
            'registros' => $historico->historico(CadastroLegislacoes::ENTIDADE, $legislacao->id),
            'campos' => ['nome' => 'Nome', 'orgao_emissor' => 'Órgão emissor', 'inicio_vigencia' => 'Início da vigência',
                'fim_vigencia' => 'Fim da vigência', 'tipos_amostra' => 'Tipos de amostra', 'substituida_por' => 'Substituída por'],
        ]);
    }

    private function formulario(Legislacao $legislacao, ?Legislacao $substituida): View
    {
        $atuais = $legislacao->exists ? $legislacao->tiposAmostra()->pluck('tipos_amostra.id')->all()
            : ($substituida?->tiposAmostra->pluck('id')->all() ?? []);
        $tipos = TipoAmostra::where('ativo', true)->orWhereIn('id', $atuais)->orderByNome()->get();

        return view('catalogo.legislacoes.form', compact('legislacao', 'substituida', 'tipos', 'atuais'));
    }
}
