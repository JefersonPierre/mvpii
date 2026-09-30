<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\RegrasAcesso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// UC01 – Autenticar usuário (RF01, RN09, RN10)
class LoginController extends Controller
{
    private const CREDENCIAIS_INVALIDAS = 'E-mail ou senha incorretos.';

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            ['email' => ['required', 'email'], 'senha' => ['required', 'string']],
            ['email.required' => 'Informe o e-mail.', 'email.email' => 'Informe um e-mail válido.', 'senha.required' => 'Informe a senha.'],
        );

        $usuario = Usuario::where('email', mb_strtolower(trim($dados['email'])))->first();
        if (! $usuario) {
            $this->falhar(self::CREDENCIAIS_INVALIDAS);
        }

        $agora = now();
        if (RegrasAcesso::estaBloqueado($usuario->bloqueado_ate, $agora)) {
            $this->falhar('Acesso bloqueado por excesso de tentativas. Tente novamente após '.$usuario->bloqueado_ate->format('H:i').'.');
        }

        if (! Hash::check($dados['senha'], $usuario->getAuthPassword())) {
            $r = RegrasAcesso::registrarFalha($usuario->tentativas_falhas, $agora);
            $usuario->forceFill(['tentativas_falhas' => $r['tentativas_falhas'], 'bloqueado_ate' => $r['bloqueado_ate']])->save();

            $this->falhar($r['bloqueou']
                ? sprintf('Acesso bloqueado por %d minutos após %d tentativas incorretas.', RegrasAcesso::MINUTOS_BLOQUEIO, RegrasAcesso::MAX_TENTATIVAS)
                : sprintf(
                    '%s Tentativa %d de %d; na quinta o acesso fica bloqueado por %d minutos.',
                    self::CREDENCIAIS_INVALIDAS, $r['tentativas_falhas'], RegrasAcesso::MAX_TENTATIVAS, RegrasAcesso::MINUTOS_BLOQUEIO,
                ));
        }

        if (! $usuario->ativo) {
            $this->falhar('Este usuário está inativo. Procure o responsável pelo sistema no laboratório.');
        }

        $usuario->forceFill(['tentativas_falhas' => 0, 'bloqueado_ate' => null])->save();
        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function falhar(string $mensagem): never
    {
        throw ValidationException::withMessages(['email' => $mensagem]);
    }
}
