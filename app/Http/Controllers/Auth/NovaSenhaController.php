<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LinksSenha;
use App\Support\RegrasAcesso;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// RF02 – criar nova senha a partir do link recebido por e-mail
class NovaSenhaController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.nova-senha', ['token' => $request->query('token')]);
    }

    public function store(Request $request, LinksSenha $links): RedirectResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'senha' => [
                'required',
                'string',
                'confirmed',
                function (string $atributo, mixed $valor, Closure $falhar) {
                    if (! RegrasAcesso::senhaAtendeRegra((string) $valor)) {
                        $falhar(RegrasAcesso::MENSAGEM_SENHA);
                    }
                },
            ],
        ], [
            'token.required' => 'Link inválido ou expirado. Solicite um novo.',
            'senha.required' => 'Informe a nova senha.',
            'senha.confirmed' => 'As senhas não conferem.',
        ]);

        $registro = $links->validar($dados['token']);
        if (! $registro) {
            return back()->withErrors(['senha' => 'Link inválido ou expirado. Solicite um novo.']);
        }
        $links->redefinir($registro, $dados['senha']);

        return redirect()->route('login')->with('sucesso', 'Senha criada. Entre com a nova senha.');
    }
}
