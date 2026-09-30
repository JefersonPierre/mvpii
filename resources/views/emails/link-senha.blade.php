Olá, {{ $usuario->nome }}.

@if ($tipo === \App\Mail\LinkSenhaMail::CONVITE)
Você foi cadastrado no sistema do laboratório. Para criar sua senha, acesse o link abaixo. Ele vale por 24 horas.

{{ $link }}
@else
Para criar uma nova senha, acesse o link abaixo. Ele vale por 1 hora.

{{ $link }}

Se você não pediu a troca, ignore este e-mail.
@endif
