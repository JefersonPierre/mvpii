<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NovaSenhaController;
use App\Http\Controllers\Auth\RecuperarSenhaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PontoColetaController;
use App\Http\Controllers\TipoAmostraController;
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

    // MOD02 – Clientes (RF04–RF08) e ficha do cliente (RF23, RF24)
    Route::patch('/clientes/{cliente}/situacao', [ClienteController::class, 'situacao'])->name('clientes.situacao');
    Route::resource('clientes', ClienteController::class)->except('destroy');

    // MOD03 – Pontos de coleta (RF09–RF11): cadastrados a partir da ficha do cliente
    Route::get('/clientes/{cliente}/pontos/create', [PontoColetaController::class, 'create'])->name('pontos.create');
    Route::post('/clientes/{cliente}/pontos', [PontoColetaController::class, 'store'])->name('pontos.store');
    Route::get('/pontos/{ponto}/edit', [PontoColetaController::class, 'edit'])->name('pontos.edit');
    Route::put('/pontos/{ponto}', [PontoColetaController::class, 'update'])->name('pontos.update');
    Route::patch('/pontos/{ponto}/situacao', [PontoColetaController::class, 'situacao'])->name('pontos.situacao');

    // MOD04 – Catálogo técnico
    Route::view('/catalogo', 'catalogo.index')->name('catalogo');
    Route::prefix('catalogo')->name('catalogo.')->group(function () {
        Route::get('/tipos-amostra/{tipo}/historico', [TipoAmostraController::class, 'historico'])->name('tipos-amostra.historico');
        Route::patch('/tipos-amostra/{tipo}/situacao', [TipoAmostraController::class, 'situacao'])->name('tipos-amostra.situacao');
        Route::resource('tipos-amostra', TipoAmostraController::class)->except(['show', 'destroy'])
            ->parameters(['tipos-amostra' => 'tipo']);
    });

    // Próximos módulos do Entregável 1
    Route::view('/orcamentos', 'em-construcao', ['titulo' => 'Orçamentos'])->name('orcamentos');
    Route::view('/configuracoes', 'em-construcao', ['titulo' => 'Configurações'])->name('configuracoes');
});
