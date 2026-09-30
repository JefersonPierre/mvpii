<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * RNF06 – cópia de segurança e restauração do banco SQLite (ambiente local).
 * Em produção com MySQL/PostgreSQL, use a cópia diária do provedor ou mysqldump/pg_dump (ver README).
 */
class CopiaSeguranca
{
    public const DIAS_RETENCAO = 30;

    public function pasta(): string
    {
        return storage_path('app/private/copias');
    }

    /** Gera uma cópia consistente (VACUUM INTO, funciona com o sistema aberto) e apaga as mais antigas que 30 dias. */
    public function copiar(): string
    {
        $this->exigirSqlite();
        File::ensureDirectoryExists($this->pasta());

        // Milissegundos no nome: a restauração faz uma cópia logo antes de restaurar.
        $arquivo = $this->pasta().'/banco-'.now()->format('Y-m-d_His_v').'.sqlite';
        DB::statement('VACUUM INTO ?', [$arquivo]);
        $this->apagarAntigas();

        return $arquivo;
    }

    /** Substitui o banco atual pela cópia informada (antes, guarda uma cópia do estado atual por segurança). */
    public function restaurar(string $arquivo): string
    {
        $this->exigirSqlite();
        $origem = File::exists($arquivo) ? $arquivo : $this->pasta().'/'.basename($arquivo);
        if (! File::exists($origem)) {
            throw new RuntimeException("Cópia não encontrada: {$arquivo}");
        }

        $antes = $this->copiar();
        DB::disconnect();
        File::copy($origem, config('database.connections.sqlite.database'));

        return $antes;
    }

    /** @return list<string> cópias, da mais recente para a mais antiga */
    public function listar(): array
    {
        return collect(File::glob($this->pasta().'/banco-*.sqlite'))->sortDesc()->values()->all();
    }

    private function apagarAntigas(): void
    {
        $limite = Carbon::now()->subDays(self::DIAS_RETENCAO)->getTimestamp();
        foreach ($this->listar() as $copia) {
            if (File::lastModified($copia) < $limite) {
                File::delete($copia);
            }
        }
    }

    private function exigirSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite' || config('database.connections.sqlite.database') === ':memory:') {
            throw new RuntimeException('Este comando é para o banco SQLite em arquivo do ambiente local. Em produção, use a cópia do provedor ou mysqldump/pg_dump.');
        }
    }
}
