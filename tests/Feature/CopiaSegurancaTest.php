<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\CopiaSeguranca;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

// RNF06 – cópia e restauração do banco SQLite. Usa um banco em arquivo próprio do teste (não o de desenvolvimento).
class CopiaSegurancaTest extends TestCase
{
    private string $banco;

    private CopiaSeguranca $copias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->banco = storage_path('framework/testing/banco-teste.sqlite');
        File::ensureDirectoryExists(dirname($this->banco));
        File::put($this->banco, '');
        config(['database.connections.sqlite.database' => $this->banco]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true]);

        $this->copias = new class extends CopiaSeguranca
        {
            public function pasta(): string
            {
                return storage_path('framework/testing/copias');
            }
        };
        File::deleteDirectory($this->copias->pasta());
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->copias->pasta());
        File::delete($this->banco);
        parent::tearDown();
    }

    public function test_copia_e_restauracao(): void
    {
        Usuario::factory()->create(['nome' => 'Antes da cópia']);
        $copia = $this->copias->copiar();
        Usuario::factory()->create(['nome' => 'Depois da cópia']);

        $this->copias->restaurar(basename($copia));
        DB::purge('sqlite');

        $this->assertSame(['Antes da cópia'], Usuario::pluck('nome')->all());
        $this->assertCount(2, $this->copias->listar(), 'A restauração guarda antes uma cópia do estado atual.');
    }

    public function test_apaga_copias_com_mais_de_30_dias(): void
    {
        $antiga = $this->copias->copiar();
        touch($antiga, now()->subDays(31)->getTimestamp());

        $recente = $this->copias->copiar();

        $this->assertFileDoesNotExist($antiga);
        $this->assertFileExists($recente);
    }
}
