<?php

namespace App\Mail;

use App\Models\Coleta;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** RF53 / RN56: laudos de uma mesma coleta, em PDF, num único e-mail ao contato principal do cliente. */
class LaudoMail extends Mailable
{
    /**
     * @param  list<array{numero: string, ponto: string, conclusao: string, pdf: string, arquivo: string}>  $laudos
     */
    public function __construct(
        public Coleta $coleta,
        public string $nomeContato,
        public array $laudos,
        public string $laboratorio,
    ) {}

    public function envelope(): Envelope
    {
        $assunto = count($this->laudos) === 1 ? "Laudo {$this->laudos[0]['numero']}" : 'Laudos da coleta de '.$this->coleta->data->format('d/m/Y');

        return new Envelope(subject: "{$assunto} – {$this->laboratorio}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.laudo');
    }

    public function attachments(): array
    {
        return array_map(fn (array $l) => Attachment::fromData(fn () => $l['pdf'], $l['arquivo'])->withMime('application/pdf'), $this->laudos);
    }
}
