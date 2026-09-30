<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD04 – Catálogo técnico: parâmetros (RF13), pacotes (RF14), legislações (RF15) e limites (RF16).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique(); // RN05
            $table->string('unidade', 30); // RN05: obrigatória
            $table->string('metodo', 150)->nullable();
            $table->decimal('limite_quantificacao', 14, 6)->nullable();
            $table->string('categoria', 20); // FISICO_QUIMICA ou MICROBIOLOGICA
            $table->decimal('preco', 10, 2); // RN05: obrigatório
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('pacotes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique(); // RN05
            $table->foreignId('tipo_amostra_id')->constrained('tipos_amostra');
            $table->decimal('preco', 10, 2); // RN05: obrigatório
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('pacote_parametro', function (Blueprint $table) {
            $table->foreignId('pacote_id')->constrained('pacotes')->cascadeOnDelete();
            $table->foreignId('parametro_id')->constrained('parametros');
            $table->primary(['pacote_id', 'parametro_id']);
        });

        // RN07: toda legislação tem início de vigência; ao ser substituída, recebe data de fim.
        Schema::create('legislacoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150)->unique();
            $table->string('orgao_emissor', 120);
            $table->date('inicio_vigencia');
            $table->date('fim_vigencia')->nullable();
            $table->foreignId('substituida_por_id')->nullable()->constrained('legislacoes');
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('legislacao_tipo_amostra', function (Blueprint $table) {
            $table->foreignId('legislacao_id')->constrained('legislacoes')->cascadeOnDelete();
            $table->foreignId('tipo_amostra_id')->constrained('tipos_amostra');
            $table->primary(['legislacao_id', 'tipo_amostra_id']);
        });

        // RN06: no máximo um limite por parâmetro, legislação e tipo de amostra.
        Schema::create('limites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislacao_id')->constrained('legislacoes');
            $table->foreignId('parametro_id')->constrained('parametros');
            $table->foreignId('tipo_amostra_id')->constrained('tipos_amostra');
            $table->string('tipo', 10); // MAXIMO, MINIMO, FAIXA ou AUSENCIA
            $table->decimal('valor_minimo', 14, 6)->nullable();
            $table->decimal('valor_maximo', 14, 6)->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['legislacao_id', 'parametro_id', 'tipo_amostra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limites');
        Schema::dropIfExists('legislacao_tipo_amostra');
        Schema::dropIfExists('legislacoes');
        Schema::dropIfExists('pacote_parametro');
        Schema::dropIfExists('pacotes');
        Schema::dropIfExists('parametros');
    }
};
