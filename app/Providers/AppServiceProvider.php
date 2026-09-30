<?php

namespace App\Providers;

use App\Services\Auditoria;
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
        // Uma instância por requisição/comando, para o modo "sistema" da auditoria valer em todos os serviços.
        $this->app->scoped(Auditoria::class);
    }

    public function boot(): void
    {
        $this->ordemAlfabeticaEmPortugues();
    }

    /**
     * Ordem alfabética do português: "Água" antes de "Efluente" e "pH" antes de "Turbidez".
     * No SQLite (desenvolvimento) registramos uma collation; no PostgreSQL usamos a collation ICU do banco, porque a
     * padrão de muitos serviços (C.UTF-8) põe maiúsculas antes de minúsculas e as letras acentuadas no fim.
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
            $collation = match ($this->getConnection()->getDriverName()) {
                'sqlite' => 'PTBR',
                'pgsql' => '"und-x-icu"',
                default => null,
            };

            return $collation
                ? $this->orderByRaw($this->getGrammar()->wrap($coluna)." COLLATE {$collation} ".($direcao === 'desc' ? 'desc' : 'asc'))
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
