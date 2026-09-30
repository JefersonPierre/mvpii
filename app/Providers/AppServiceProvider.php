<?php

namespace App\Providers;

use Collator;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pdo\Sqlite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->ordemAlfabeticaEmPortugues();
    }

    /**
     * Ordem alfabética do português: "Água" antes de "Efluente" e "pH" antes de "Turbidez".
     * MySQL e PostgreSQL já fazem isso pela collation do banco; no SQLite (desenvolvimento) registramos uma.
     */
    private function ordemAlfabeticaEmPortugues(): void
    {
        $collator = new Collator('pt_BR');

        Event::listen(function (ConnectionEstablished $evento) use ($collator) {
            $pdo = $evento->connection->getPdo();
            if ($pdo instanceof Sqlite) {
                $pdo->createCollation('PTBR', fn ($a, $b) => $collator->compare((string) $a, (string) $b));
            }
        });

        // Uso: ->orderByNome() ou ->orderByNome('identificacao').
        Builder::macro('orderByNome', function (string $coluna = 'nome', string $direcao = 'asc') {
            /** @var Builder $this */
            return $this->getConnection()->getDriverName() === 'sqlite'
                ? $this->orderByRaw($this->getGrammar()->wrap($coluna).' COLLATE PTBR '.($direcao === 'desc' ? 'desc' : 'asc'))
                : $this->orderBy($coluna, $direcao);
        });

        // Para listas já carregadas (models ou textos).
        Collection::macro('ordenarPorNome', function (string $campo = 'nome') use ($collator) {
            $valor = fn ($item) => is_scalar($item) ? (string) $item : (string) data_get($item, $campo);

            /** @var Collection $this */
            return $this->sort(fn ($a, $b) => $collator->compare($valor($a), $valor($b)))->values();
        });
    }
}
