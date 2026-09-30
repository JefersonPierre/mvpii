<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// RF22 – configurações comerciais do orçamento (uma única linha).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('validade_dias');
            $table->decimal('taxa_coleta', 10, 2);
            $table->decimal('desconto_maximo', 5, 2); // RN14, em %
            $table->text('condicoes_comerciais')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        // Valores iniciais; o laboratório ajusta em Configurações.
        DB::table('configuracoes')->insert([
            'validade_dias' => 30,
            'taxa_coleta' => 50,
            'desconto_maximo' => 15,
            'condicoes_comerciais' => "Pagamento: à vista, por PIX ou boleto, em até 15 dias após a entrega dos resultados.\n"
                .'Prazo de entrega dos resultados: até 10 dias úteis após a coleta.',
            'criado_em' => now(),
            'atualizado_em' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
