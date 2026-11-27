<?php

namespace App\Http\Middleware;

use App\Models\Dispositivo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN39 / RNF16: a API do celular aceita só o token do aparelho, válido por 12 horas e revogável pelo Administrador.
 * O usuário do token passa a ser o usuário da requisição, como numa tela comum (auditoria, permissões).
 */
class AutenticarDispositivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $dispositivo = $token ? Dispositivo::with('usuario')->where('token_hash', hash('sha256', $token))->first() : null;

        if (! $dispositivo || ! $dispositivo->valido() || ! $dispositivo->usuario->ativo) {
            return response()->json([
                'mensagem' => 'O acesso deste aparelho venceu. Entre no sistema de novo, com conexão, para enviar os registros.',
                'codigo' => 'ACESSO_VENCIDO',
            ], 401);
        }
        if (! $dispositivo->usuario->can('coletas.registrar')) {
            return response()->json(['mensagem' => 'Este usuário não registra coletas.', 'codigo' => 'SEM_PERMISSAO'], 403);
        }

        $dispositivo->forceFill(['ultimo_uso_em' => now()])->save();
        Auth::setUser($dispositivo->usuario);
        $request->attributes->set('dispositivo', $dispositivo);

        return $next($request);
    }
}
