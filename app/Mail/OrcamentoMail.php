<?php

namespace App\Mail;

use App\Models\Orcamento;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/** RF18: e-mail ao contato do cliente com o orçamento em PDF anexo. */
class OrcamentoMail extends Mailable
{
    public function __construct(
        public Orcamento $orcamento,
        public string $mensagem,
        public Carbon $validoAte,
        private readonly string $pdf,
        private readonly string $nomeArquivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Orçamento {$this->orcamento->numero()} – ".config('laboratorio.dados.nome'));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.orcamento');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->nomeArquivo)->withMime('application/pdf')];
    }
}
