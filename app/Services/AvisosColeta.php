<?php

namespace App\Services;

use App\Mail\ColetaMail;
use App\Models\Coleta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

// RF30 / RN37: avisa o contato principal do cliente da confirmação, do reagendamento ou do cancelamento da coleta.
class AvisosColeta
{
    /**
     * Envia o aviso. Devolve null se enviou ou a mensagem para o usuário avisar o cliente por outro meio
     * (contato sem e-mail ou falha no envio). A falha no e-mail não desfaz o agendamento.
     */
    public function avisar(Coleta $coleta, string $tipo): ?string
    {
        $coleta->load(['orcamento.cliente.contatoPrincipal', 'coletor', 'amostras.pontoColeta']);
        $contato = $coleta->orcamento->cliente->contatoPrincipal;

        if (! $contato?->email) {
            return 'O contato principal do cliente não tem e-mail: comunique o cliente por outro meio.';
        }

        try {
            Mail::to($contato->email)->send(new ColetaMail($coleta, $tipo, $contato->nome));
        } catch (Throwable $erro) {
            Log::error("Falha ao enviar o aviso da coleta {$coleta->id}: {$erro->getMessage()}");

            return 'Não foi possível enviar o e-mail ao cliente agora: comunique o cliente por outro meio.';
        }

        return null;
    }

    /** Para a mensagem de sucesso da tela. */
    public function destinatario(Coleta $coleta): ?string
    {
        return $coleta->orcamento->cliente->contatoPrincipal?->email;
    }
}
