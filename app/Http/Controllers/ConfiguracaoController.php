<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfiguracaoRequest;
use App\Models\Configuracao;
use App\Services\CadastroComHistorico;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// UC12 – Configurar parâmetros comerciais (RF22, RN08, RN14)
class ConfiguracaoController extends Controller
{
    private const ENTIDADE = 'CONFIGURACOES';

    private const CAMPOS_AUDITADOS = ['validade_dias', 'taxa_coleta', 'desconto_maximo', 'condicoes_comerciais'];

    public function __construct(private readonly CadastroComHistorico $cadastro) {}

    public function edit(): View
    {
        $configuracao = Configuracao::atual();
        $registros = $this->cadastro->historico(self::ENTIDADE, $configuracao->id);

        return view('configuracoes.edit', compact('configuracao', 'registros'));
    }

    /** Vale para os próximos orçamentos; os já emitidos guardam os próprios valores. */
    public function update(ConfiguracaoRequest $request): RedirectResponse
    {
        $this->cadastro->salvar(Configuracao::atual(), $request->validated(), self::ENTIDADE, self::CAMPOS_AUDITADOS);

        return redirect()->route('configuracoes')->with('sucesso', 'Configurações salvas. Elas valem para os próximos orçamentos.');
    }
}
