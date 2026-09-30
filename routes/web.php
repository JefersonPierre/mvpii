<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NovaSenhaController;
use App\Http\Controllers\Auth\RecuperarSenhaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\UsuarioAtivo;
use Illuminate\Support\Facades\Route;

// MOD01 – Acesso (RF01, RF02)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/recuperar-senha', [RecuperarSenhaController::class, 'create'])->name('recuperar-senha');
    Route::post('/recuperar-senha', [RecuperarSenhaController::class, 'store'])->middleware('throttle:5,1');
});

// O link do e-mail funciona mesmo com alguém conectado no navegador.
Route::get('/nova-senha', [NovaSenhaController::class, 'create'])->name('nova-senha');
Route::post('/nova-senha', [NovaSenhaController::class, 'store']);

Route::middleware(['auth', UsuarioAtivo::class])->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('sair');

    Route::view('/', 'inicio')->name('inicio');

    // MOD01 – Usuários (RF03)
    Route::get('/usuarios/{usuario}/historico', [UsuarioController::class, 'historico'])->name('usuarios.historico');
    Route::patch('/usuarios/{usuario}/situacao', [UsuarioController::class, 'situacao'])->name('usuarios.situacao');
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])
        ->parameters(['usuarios' => 'usuario']);

    // Próximos módulos do Entregável 1
    Route::view('/orcamentos', 'em-construcao', ['titulo' => 'Orçamentos'])->name('orcamentos');
    Route::view('/clientes', 'em-construcao', ['titulo' => 'Clientes'])->name('clientes');
    Route::view('/catalogo', 'em-construcao', ['titulo' => 'Catálogo técnico'])->name('catalogo');
    Route::view('/configuracoes', 'em-construcao', ['titulo' => 'Configurações'])->name('configuracoes');
});
