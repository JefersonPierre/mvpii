<?php

use App\Services\CadastroOrcamentos;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// RN17: orçamentos enviados e sem resposta após a validade passam para Expirado.
Artisan::command('orcamentos:expirar', function (CadastroOrcamentos $orcamentos) {
    $this->info($orcamentos->expirarVencidos().' orçamento(s) expirado(s).');
})->purpose('Marca como Expirado os orçamentos enviados com a validade vencida (RN17)');

// Em produção, o cron do servidor chama "php artisan schedule:run" a cada minuto.
Schedule::command('orcamentos:expirar')->dailyAt('00:10');
