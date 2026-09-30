<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** RN10: se o usuário for inativado durante a sessão, ele é desconectado no próximo acesso. */
class UsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->ativo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Este usuário está inativo. Procure o responsável pelo sistema no laboratório.']);
        }

        return $next($request);
    }
}
