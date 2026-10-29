<?php

namespace App\Http\Controllers;

use App\Services\AcessoDispositivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// MOD10 – UC21 / UC22: aplicativo do coletor no celular (RF38–RF41). A tela é guardada pelo service worker e funciona
// sem conexão; os dados vêm da API com o token do aparelho (RN39).
class CampoController extends Controller
{
    public function index(): View
    {
        return view('campo.index');
    }

    /** RN39: com conexão e já autenticado, o aparelho recebe um token de 12 horas para usar a API. */
    public function conectar(Request $request, AcessoDispositivos $acesso): JsonResponse
    {
        $dados = $request->validate(['nome' => ['nullable', 'string', 'max:255'], 'token_anterior' => ['nullable', 'string', 'max:100']]);

        return response()->json([
            ...$acesso->conectar($request->user(), $dados['nome'] ?? (string) $request->userAgent(), $dados['token_anterior'] ?? null),
            'usuario' => ['id' => $request->user()->id, 'nome' => $request->user()->nome],
        ]);
    }
}
