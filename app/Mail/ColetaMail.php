<?php

namespace App\Mail;

use App\Models\Coleta;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** RF30: aviso ao contato principal do cliente sobre a coleta (confirmação, reagendamento ou cancelamento). */
class ColetaMail extends Mailable
{
    public const CONFIRMACAO = 'CONFIRMACAO';

    public const REAGENDAMENTO = 'REAGENDAMENTO';

    public const CANCELAMENTO = 'CANCELAMENTO';

    private const ASSUNTOS = [
        self::CONFIRMACAO => 'Coleta agendada',
        self::REAGENDAMENTO => 'Coleta reagendada',
        self::CANCELAMENTO => 'Coleta cancelada',
    ];

    public function __construct(public Coleta $coleta, public string $tipo, public string $nomeContato) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::ASSUNTOS[$this->tipo].' para '.$this->coleta->data->format('d/m/Y').' – '
            .config('laboratorio.dados.nome'));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.coleta');
    }
}
