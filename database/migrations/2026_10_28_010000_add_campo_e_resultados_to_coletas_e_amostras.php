<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD10 / MOD11 – registro pelo celular e ordem de visita; MOD12 – situações da amostra após o recebimento.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coletas', function (Blueprint $table) {
            $table->unsignedSmallInteger('ordem_visita')->nullable(); // RN47: ordem alterada manualmente, só naquele dia
            // RN41: COMPUTADOR, CELULAR ou CELULAR_SEM_CONEXAO; data e hora em que o registro chegou ao servidor.
            $table->string('origem_registro', 20)->nullable();
            $table->dateTime('registro_recebido_em')->nullable();
            // RN44: localização gravada pelo celular no momento da coleta.
            $table->decimal('latitude_coleta', 10, 7)->nullable();
            $table->decimal('longitude_coleta', 10, 7)->nullable();
            $table->unsignedInteger('distancia_ponto_metros')->nullable();
            $table->boolean('conferir_localizacao')->default(false);
        });

        Schema::table('amostras', function (Blueprint $table) {
            // RN33 ampliada: EM_ANALISE, EM_REVISAO, LIBERADA e LAUDO_EMITIDO depois de RECEBIDA.
            $table->string('situacao', 15)->default('PREVISTA')->change();

            // MOD12 – Resultados e conformidade (RF49–RF51)
            $table->foreignId('legislacao_id')->nullable()->constrained('legislacoes'); // RN51
            $table->string('conformidade', 15)->nullable(); // CONFORME, NAO_CONFORME, INCONCLUSIVA ou SEM_LEGISLACAO
            $table->dateTime('enviada_revisao_em')->nullable();
            $table->string('motivo_devolucao', 255)->nullable(); // RN53
            $table->dateTime('liberada_em')->nullable();
            $table->foreignId('liberada_por_id')->nullable()->constrained('usuarios');
            $table->string('motivo_retificacao', 255)->nullable(); // RN55: retificação em andamento
        });
    }

    public function down(): void
    {
        Schema::table('amostras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legislacao_id');
            $table->dropConstrainedForeignId('liberada_por_id');
            $table->dropColumn(['conformidade', 'enviada_revisao_em', 'motivo_devolucao', 'liberada_em', 'motivo_retificacao']);
        });
        Schema::table('coletas', function (Blueprint $table) {
            $table->dropColumn(['ordem_visita', 'origem_registro', 'registro_recebido_em', 'latitude_coleta', 'longitude_coleta',
                'distancia_ponto_metros', 'conferir_localizacao']);
        });
    }
};
