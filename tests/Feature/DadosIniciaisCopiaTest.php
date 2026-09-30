<?php

namespace Tests\Feature;

use App\Models\Legislacao;
use App\Models\Limite;
use App\Models\Parametro;
use Database\Seeders\CatalogoExemploSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Dados iniciais de exemplo (seção 3 da documentação)
class DadosIniciaisCopiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_de_exemplo_pode_rodar_mais_de_uma_vez_sem_duplicar(): void
    {
        $this->seed(CatalogoExemploSeeder::class);
        $this->seed(CatalogoExemploSeeder::class);

        $this->assertSame(20, Parametro::count());
        $this->assertSame(2, Legislacao::count());
        $this->assertSame(22, Limite::count());

        // As faixas respeitam a RN06 (mínimo menor que o máximo).
        Limite::where('tipo', 'FAIXA')->get()
            ->each(fn (Limite $l) => $this->assertLessThan((float) $l->valor_maximo, (float) $l->valor_minimo));

        $ph = Limite::whereHas('parametro', fn ($q) => $q->where('nome', 'pH'))
            ->whereHas('legislacao', fn ($q) => $q->where('nome', 'Portaria GM/MS nº 888/2021'))->sole();
        $this->assertSame('6 a 9 adimensional', $ph->descricao());
    }

    public function test_copia_recusa_banco_em_memoria(): void
    {
        $this->artisan('banco:copiar')->assertFailed();
    }
}
