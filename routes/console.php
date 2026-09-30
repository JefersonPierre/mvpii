<?php

use App\Services\CadastroOrcamentos;
use App\Services\CopiaSeguranca;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// RN17: orçamentos enviados e sem resposta após a validade passam para Expirado.
Artisan::command('orcamentos:expirar', function (CadastroOrcamentos $orcamentos) {
    $this->info($orcamentos->expirarVencidos().' orçamento(s) expirado(s).');
})->purpose('Marca como Expirado os orçamentos enviados com a validade vencida (RN17)');

// RNF06: cópia de segurança do banco local (SQLite), com retenção de 30 dias.
Artisan::command('banco:copiar', function (CopiaSeguranca $copias) {
    try {
        $this->info('Cópia criada: '.$copias->copiar());
    } catch (RuntimeException $erro) {
        $this->error($erro->getMessage());

        return 1;
    }
})->purpose('Cria uma cópia de segurança do banco SQLite (RNF06)');

Artisan::command('banco:restaurar {arquivo? : nome ou caminho da cópia; sem ele, lista as cópias}', function (CopiaSeguranca $copias) {
    $arquivo = $this->argument('arquivo');
    if (! $arquivo) {
        $this->line('Cópias disponíveis (mais recente primeiro):');
        foreach ($copias->listar() as $copia) {
            $this->line('  '.basename($copia));
        }
        $this->line('Para restaurar: php artisan banco:restaurar <nome-da-copia>');

        return;
    }
    if (! $this->confirm("O banco atual será substituído por {$arquivo}. Uma cópia do estado atual será guardada antes. Continuar?")) {
        return;
    }
    try {
        $this->info('Banco restaurado. O estado anterior foi guardado em: '.$copias->restaurar($arquivo));
    } catch (RuntimeException $erro) {
        $this->error($erro->getMessage());

        return 1;
    }
})->purpose('Restaura o banco SQLite a partir de uma cópia (RNF06)');

// Em produção, o cron do servidor chama "php artisan schedule:run" a cada minuto.
Schedule::command('orcamentos:expirar')->dailyAt('00:10');
Schedule::command('banco:copiar')->dailyAt('02:00')->when(fn () => config('database.default') === 'sqlite');
