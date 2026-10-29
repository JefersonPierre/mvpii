<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\Usuario;
use App\Services\AcessoDispositivos;
use Illuminate\Http\RedirectResponse;

// RNF16: o Administrador revoga o acesso de um aparelho do usuário.
class DispositivoController extends Controller
{
    public function revogar(Usuario $usuario, Dispositivo $dispositivo, AcessoDispositivos $acesso): RedirectResponse
    {
        abort_unless($dispositivo->usuario_id === $usuario->id, 404);
        $acesso->revogar($dispositivo);

        return redirect()->route('usuarios.edit', $usuario)->with('sucesso', 'Acesso do aparelho revogado. Os registros ainda não enviados por ele precisarão de um novo login.');
    }
}
