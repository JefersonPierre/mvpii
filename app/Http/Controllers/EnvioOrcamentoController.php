<?php

namespace App\Http\Controllers;

use App\Models\Orcamento;
use App\Services\EnvioOrcamentos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

// UC09 – Enviar orçamento (RF18, RF19)
class EnvioOrcamentoController extends Controller
{
    public function __construct(private readonly EnvioOrcamentos $envio) {}

    /** Pré-visualização do PDF e confirmação do destinatário e da mensagem. */
    public function create(Orcamento $orcamento): View|RedirectResponse
    {
        if (! $orcamento->editavel()) {
            return redirect()->route('orcamentos.show', $orcamento)->with('erro', 'Este orçamento já foi enviado.');
        }
        $orcamento->load(['cliente.contatos', 'contato']);
        $mensagem = "Olá, {$this->saudacao($orcamento)}.\n\nSegue em anexo o orçamento {$orcamento->numero()} para as análises solicitadas.\n"
            .'Ficamos à disposição para qualquer dúvida.';

        return view('orcamentos.envio', compact('orcamento', 'mensagem'));
    }

    public function store(Request $request, Orcamento $orcamento): RedirectResponse
    {
        $manual = $request->input('forma') === 'manual';
        $dados = $request->validate([
            'email' => [$manual ? 'nullable' : 'required', 'email', 'max:150'],
            'mensagem' => ['nullable', 'string', 'max:5000'],
        ], ['email.required' => 'Informe o e-mail do destinatário ou registre o envio manual.']);

        try {
            $this->envio->enviar($orcamento, $manual ? null : $dados['email'], $dados['mensagem'] ?? null);
        } catch (RuntimeException $erro) {
            return back()->withInput()->with('erro', $erro->getMessage());
        }

        return redirect()->route('orcamentos.show', $orcamento)->with('sucesso', $manual
            ? 'Envio registrado. Entregue o PDF ao cliente pelo meio combinado.'
            : "Orçamento enviado para {$dados['email']}.");
    }

    /** PDF para ver no navegador (prévia do rascunho ou o arquivo enviado). */
    public function pdf(Request $request, Orcamento $orcamento): Response
    {
        $disposicao = $request->boolean('baixar') ? 'attachment' : 'inline';

        return response($this->envio->pdfGuardadoOuPrevia($orcamento), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicao.'; filename="'.$this->envio->nomeArquivo($orcamento).'"',
        ]);
    }

    private function saudacao(Orcamento $orcamento): string
    {
        return $orcamento->contato?->nome ?? $orcamento->cliente->nome;
    }
}
