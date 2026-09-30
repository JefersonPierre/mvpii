<?php

namespace App\Providers;

use Collator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Ordena em ordem alfabética do português ("Água" antes de "Efluente"), o que o SQLite não faz.
        Collection::macro('ordenarPorNome', function (string $campo = 'nome') {
            $collator = new Collator('pt_BR');

            /** @var Collection $this */
            return $this->sort(fn ($a, $b) => $collator->compare(data_get($a, $campo), data_get($b, $campo)))->values();
        });
    }
}
