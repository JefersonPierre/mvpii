<?php

namespace App\Http\Controllers;

use App\Models\FotoAmostra;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// RN45: fotos da coleta, vistas por quem pode consultar a amostra (RN36).
class FotoAmostraController extends Controller
{
    public function __invoke(FotoAmostra $foto): StreamedResponse
    {
        Gate::authorize('view', $foto->amostra);
        abort_unless(Storage::disk('local')->exists($foto->caminho), 404);

        return Storage::disk('local')->response($foto->caminho, basename($foto->caminho), ['Cache-Control' => 'private, max-age=86400']);
    }
}
