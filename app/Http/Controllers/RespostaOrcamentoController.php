<?php

namespace App\Http\Controllers;

use App\Models\Orcamento;
use App\Services\CadastroOrcamentos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// UC10 – Registrar resposta do orçamento (RF19, RN17, RN18, RN19)
class RespostaOrcamentoController extends Controller
{
    public function __construct(private readonly CadastroOrcamentos $orcamentos) {}

    public function store(Request $request, Orcamento $orcamento): RedirectResponse
    {
        $this->orcamentos->expirarVencidos(); // RN17: vencido não recebe mais resposta
        $orcamento->refresh();
        if ($orcamento->situacao !== Orcamento::ENVIADO) {
            return $this->voltar($orcamento)->with('erro', $orcamento->situacao === Orcamento::EXPIRADO
                ? 'A validade deste orçamento venceu. Para retomar, crie uma nova revisão.'
                : 'Só é possível registrar a resposta de um orçamento enviado.');
        }

        $dados = $request->validate([
            'resultado' => ['required', Rule::in([Orcamento::APROVADO, Orcamento::RECUSADO])],
            'data_resposta' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$orcamento->data_envio->toDateString()],
            // RN19: a recusa exige o motivo.
            'motivo_recusa' => ['nullable', 'required_if:resultado,RECUSADO', Rule::in(array_keys(Orcamento::MOTIVOS_RECUSA))],
            'motivo_detalhe' => ['nullable', 'required_if:motivo_recusa,OUTRO', 'string', 'max:255'],
        ], [
            'resultado.required' => 'Escolha Aprovado ou Recusado.',
            'data_resposta.required' => 'Informe a data da resposta.',
            'data_resposta.before_or_equal' => 'A data da resposta não pode ser futura.',
            'data_resposta.after_or_equal' => 'A resposta não pode ser anterior ao envio ('.$orcamento->data_envio->format('d/m/Y').').',
            'motivo_recusa.required_if' => 'Informe o motivo: preço, prazo, desistência ou outro.',
            'motivo_detalhe.required_if' => 'Descreva o motivo da recusa.',
        ]);

        if ($dados['resultado'] === Orcamento::APROVADO) {
            // RN18: aprovar exige cadastro completo (CPF/CNPJ e endereço).
            if (! $orcamento->cliente->cadastroCompleto()) {
                return $this->voltar($orcamento)->withInput()->with('erro',
                    'Complete o cadastro do cliente (CPF/CNPJ e endereço) antes de aprovar o orçamento.');
            }
            $this->orcamentos->mudarSituacao($orcamento, Orcamento::APROVADO, ['data_resposta' => $dados['data_resposta']]);

            return $this->voltar($orcamento)->with('sucesso',
                'Orçamento aprovado. Confira os pontos de coleta do cliente para o agendamento da coleta.');
        }

        $this->orcamentos->mudarSituacao($orcamento, Orcamento::RECUSADO, [
            'data_resposta' => $dados['data_resposta'],
            'motivo_recusa' => $dados['motivo_recusa'],
            'motivo_detalhe' => $dados['motivo_detalhe'] ?? null,
        ]);

        return $this->voltar($orcamento)->with('sucesso', 'Recusa registrada.');
    }

    private function voltar(Orcamento $orcamento): RedirectResponse
    {
        return redirect()->route('orcamentos.show', $orcamento);
    }
}
