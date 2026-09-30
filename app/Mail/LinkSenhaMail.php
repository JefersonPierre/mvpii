<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail com o link para criar a senha: convite de novo usuário ou recuperação (RF02, RF03). */
class LinkSenhaMail extends Mailable
{
    public const CONVITE = 'convite';

    public const RECUPERACAO = 'recuperacao';

    public function __construct(
        public Usuario $usuario,
        public string $link,
        public string $tipo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tipo === self::CONVITE ? 'Seu acesso ao sistema do laboratório' : 'Criar nova senha',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.link-senha');
    }
}
