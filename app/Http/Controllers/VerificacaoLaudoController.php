<?php

namespace App\Http\Controllers;

use App\Models\Laudo;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC28 1a / RF55 / RN57: página pública – mostra só se o laudo é autêntico e se está vigente, nunca os resultados.
class VerificacaoLaudoController extends Controller
{
    public function __invoke(Request $request, ?string $codigo = null): View
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($codigo ?? $request->query('codigo'))));
        $laudo = $codigo !== '' ? Laudo::with(['amostra.coleta.orcamento.cliente', 'amostra.pontoColeta', 'substituidoPor'])
            ->where('codigo_verificacao', $codigo)->first() : null;

        return view('laudos.verificar', ['codigo' => $codigo, 'laudo' => $laudo]);
    }
}
