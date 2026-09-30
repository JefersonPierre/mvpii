<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LinkSenhaMail;
use App\Models\Usuario;
use App\Services\LinksSenha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// RF02 – Recuperar senha por e-mail
class RecuperarSenhaController extends Controller
{
    public function create(): View
    {
        return view('auth.recuperar-senha');
    }

    /** A resposta é a mesma exista ou não o e-mail, para não revelar quem tem cadastro. */
    public function store(Request $request, LinksSenha $links): RedirectResponse
    {
        $dados = $request->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Informe o e-mail.', 'email.email' => 'Informe um e-mail válido.'],
        );

        $usuario = Usuario::where('email', mb_strtolower(trim($dados['email'])))->where('ativo', true)->first();
        $link = $usuario ? $links->enviar($usuario, LinkSenhaMail::RECUPERACAO) : null;

        return back()
            ->with('sucesso', 'Se o e-mail estiver cadastrado, enviamos um link para criar uma nova senha. O link vale por 1 hora.')
            ->with('link_local', LinksSenha::exibirNaTela($link));
    }
}
