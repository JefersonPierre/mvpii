<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD04 (RF12) – tipos de amostra e MOD03 (RF09–RF11) – pontos de coleta.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_amostra', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80)->unique(); // RN05
            $table->string('descricao', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        // RN03: todo ponto pertence a exatamente um cliente e tem um tipo de amostra padrão.
        Schema::create('pontos_coleta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('identificacao', 150); // ex.: "Reservatório superior – Bloco A"
            $table->foreignId('tipo_amostra_id')->constrained('tipos_amostra');
            $table->char('cep', 8)->nullable();
            $table->string('logradouro', 150);
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80);
            $table->char('uf', 2);
            $table->string('referencia', 255)->nullable(); // como chegar ao ponto
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('ativo')->default(true); // RN04
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pontos_coleta');
        Schema::dropIfExists('tipos_amostra');
    }
};
