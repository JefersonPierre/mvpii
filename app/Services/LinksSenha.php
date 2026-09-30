<?php

namespace App\Services;

use App\Mail\LinkSenhaMail;
use App\Models\TokenSenha;
use App\Models\Usuario;
use App\Support\RegrasAcesso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// RF02: links por e-mail para criar a senha (recuperação ou convite de novo usuário).
class LinksSenha
{
    /** Gera o link, envia por e-mail e devolve a URL (usada só para exibir no ambiente local). */
    public function enviar(Usuario $usuario, string $tipo): string
    {
        $minutos = $tipo === LinkSenhaMail::CONVITE
            ? RegrasAcesso::MINUTOS_VALIDADE_CONVITE
            : RegrasAcesso::MINUTOS_VALIDADE_LINK;

        $token = Str::random(64);
        $usuario->tokensSenha()->create([
            'token_hash' => hash('sha256', $token),
            'expira_em' => now()->addMinutes($minutos),
        ]);

        $link = route('nova-senha', ['token' => $token]);
        Mail::to($usuario->email)->send(new LinkSenhaMail($usuario, $link, $tipo));

        return $link;
    }

    /**
     * No ambiente local os e-mails vão para o log (MAIL_MAILER=log); por isso o link também aparece na tela,
     * para permitir testar o fluxo; o mesmo vale para a publicação de demonstração. Em produção devolve null e o link
     * só chega por e-mail.
     */
    public static function exibirNaTela(?string $link): ?string
    {
        return (app()->isLocal() || config('laboratorio.demonstracao')) && config('mail.default') === 'log' ? $link : null;
    }

    /** Devolve o token se ele existir, não tiver sido usado e estiver no prazo. */
    public function validar(?string $token): ?TokenSenha
    {
        if (! $token) {
            return null;
        }
        $registro = TokenSenha::where('token_hash', hash('sha256', $token))->first();
        if (! $registro || $registro->usado_em || $registro->expira_em->isPast()) {
            return null;
        }

        return $registro;
    }

    /** Troca a senha e invalida o link. Também desbloqueia o acesso (RN09). */
    public function redefinir(TokenSenha $registro, string $novaSenha): void
    {
        DB::transaction(function () use ($registro, $novaSenha) {
            $registro->usuario->forceFill([
                'senha' => $novaSenha,
                'tentativas_falhas' => 0,
                'bloqueado_ate' => null,
            ])->save();
            $registro->update(['usado_em' => now()]);
        });
    }
}
