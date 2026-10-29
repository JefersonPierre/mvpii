<?php

use App\Http\Controllers\Api\CampoApiController;
use App\Http\Middleware\AutenticarDispositivo;
use Illuminate\Support\Facades\Route;

// MOD10 – API do celular do coletor (RF39, RF41, RN38–RN42): acesso pelo token do aparelho, sem sessão.
Route::middleware([AutenticarDispositivo::class, 'throttle:120,1'])->group(function () {
    Route::get('/campo/coletas', [CampoApiController::class, 'coletas']);
    Route::post('/campo/ordem', [CampoApiController::class, 'ordem']);
    Route::post('/sincronizacao/coletas', [CampoApiController::class, 'sincronizar']);
});
