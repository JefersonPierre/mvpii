<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD12 – Resultados e conformidade (RF48–RF51) e MOD13 – Laudos (RF52–RF55).
return new class extends Migration
{
    public function up(): void
    {
        // RN50: um resultado por parâmetro da amostra. O limite é copiado no lançamento, para o laudo não mudar se o
        // catálogo mudar depois.
        Schema::create('resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amostra_id')->constrained('amostras');
            $table->foreignId('parametro_id')->constrained('parametros');
            $table->decimal('valor', 14, 6)->nullable(); // na unidade do parâmetro
            $table->boolean('menor_que_lq')->default(false); // "< LQ"
            $table->string('presenca', 8)->nullable(); // PRESENTE ou AUSENTE (limite do tipo Ausência)
            $table->date('data_analise')->nullable();
            $table->string('observacoes', 255)->nullable();
            $table->string('origem', 12)->default('LABORATORIO'); // CAMPO (RN46) ou LABORATORIO
            $table->foreignId('limite_id')->nullable()->constrained('limites');
            $table->string('limite_tipo', 10)->nullable();
            $table->decimal('limite_minimo', 14, 6)->nullable();
            $table->decimal('limite_maximo', 14, 6)->nullable();
            // RN52: CONFORME, NAO_CONFORME, INCONCLUSIVO ou SEM_LIMITE; vazio enquanto não há resultado.
            $table->string('conformidade', 12)->nullable();
            $table->foreignId('lancado_por_id')->nullable()->constrained('usuarios');
            $table->dateTime('lancado_em')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['amostra_id', 'parametro_id']);
        });

        // RN54 / RN55: um laudo por amostra, LAU-2026-0001; cada retificação mantém o número e incrementa a revisão.
        Schema::create('laudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amostra_id')->constrained('amostras');
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('sequencia');
            $table->unsignedSmallInteger('revisao')->default(1);
            $table->foreignId('substitui_id')->nullable()->constrained('laudos');
            $table->string('motivo_retificacao', 255)->nullable();
            $table->string('codigo_verificacao', 12)->unique(); // RN57
            $table->string('situacao', 11)->default('VIGENTE')->index(); // VIGENTE ou SUBSTITUIDO
            $table->string('conclusao', 15); // conformidade da amostra na emissão
            $table->string('pdf_caminho', 255);
            $table->char('pdf_hash', 64); // RNF17: SHA-256 do PDF guardado
            $table->foreignId('emitido_por_id')->constrained('usuarios');
            $table->dateTime('emitido_em');
            $table->string('envio_email', 150)->nullable(); // RN56
            $table->dateTime('enviado_em')->nullable();
            $table->foreignId('enviado_por_id')->nullable()->constrained('usuarios');
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['ano', 'sequencia', 'revisao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laudos');
        Schema::dropIfExists('resultados');
    }
};
