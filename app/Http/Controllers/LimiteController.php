<?php

namespace App\Http\Controllers;

use App\Http\Requests\LimiteRequest;
use App\Models\Legislacao;
use App\Models\Limite;
use App\Models\Parametro;
use App\Services\CadastroLegislacoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// UC06 – limites de cada parâmetro por legislação e tipo de amostra (RF16, RN06, RN07)
class LimiteController extends Controller
{
    public function __construct(private readonly CadastroLegislacoes $cadastro) {}

    public function create(Legislacao $legislacao): View|RedirectResponse
    {
        return $this->encerrada($legislacao) ?? $this->formulario($legislacao, new Limite(['tipo' => 'MAXIMO']));
    }

    public function store(LimiteRequest $request, Legislacao $legislacao): RedirectResponse
    {
        if ($bloqueio = $this->encerrada($legislacao)) {
            return $bloqueio;
        }
        $this->cadastro->salvarLimite($legislacao, new Limite, $request->dadosDoLimite());

        return $this->voltar($legislacao, 'Limite cadastrado.');
    }

    public function edit(Limite $limite): View|RedirectResponse
    {
        return $this->encerrada($limite->legislacao) ?? $this->formulario($limite->legislacao, $limite);
    }

    public function update(LimiteRequest $request, Limite $limite): RedirectResponse
    {
        if ($bloqueio = $this->encerrada($limite->legislacao)) {
            return $bloqueio;
        }
        $this->cadastro->salvarLimite($limite->legislacao, $limite, $request->dadosDoLimite());

        return $this->voltar($limite->legislacao, 'Limite atualizado.');
    }

    public function destroy(Limite $limite): RedirectResponse
    {
        $legislacao = $limite->legislacao;
        if ($bloqueio = $this->encerrada($legislacao)) {
            return $bloqueio;
        }
        $this->cadastro->removerLimite($limite);

        return $this->voltar($legislacao, 'Limite removido.');
    }

    private function formulario(Legislacao $legislacao, Limite $limite): View
    {
        $parametros = Parametro::where('ativo', true)->orWhere('id', $limite->parametro_id)->orderByNome()->get();
        $tipos = $legislacao->tiposAmostra;

        return view('catalogo.legislacoes.limite-form', compact('legislacao', 'limite', 'parametros', 'tipos'));
    }

    /** RN07: legislação encerrada mantém os limites só para consulta histórica. */
    private function encerrada(Legislacao $legislacao): ?RedirectResponse
    {
        return $legislacao->encerrada()
            ? $this->voltar($legislacao, null)->with('erro', 'Legislação encerrada: os limites ficam apenas para consulta.')
            : null;
    }

    private function voltar(Legislacao $legislacao, ?string $mensagem): RedirectResponse
    {
        $resposta = redirect()->route('catalogo.legislacoes.show', $legislacao);

        return $mensagem ? $resposta->with('sucesso', $mensagem) : $resposta;
    }
}
