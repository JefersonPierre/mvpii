<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * RF38 / RNF14: service worker do aplicativo do coletor. Guarda no aparelho a tela de coletas do dia e os arquivos do
 * build (CSS/JS com nome versionado), para o sistema abrir sem conexão. A versão muda a cada build.
 */
class ServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $manifesto = public_path('build/manifest.json');
        $arquivos = File::exists($manifesto)
            ? collect(json_decode(File::get($manifesto), true))->flatMap(fn ($e) => [$e['file'], ...($e['css'] ?? [])])
                ->unique()->map(fn ($f) => '/build/'.$f)->values()->all()
            : [];
        $versao = substr(md5(implode('|', $arquivos).(File::exists($manifesto) ? File::lastModified($manifesto) : '')), 0, 12);

        return response()
            ->view('campo.sw', ['arquivos' => [...$arquivos, '/manifest.webmanifest', '/icones/icone-192.png', '/icones/icone-512.png'], 'versao' => $versao])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache')
            ->header('Service-Worker-Allowed', '/');
    }
}
