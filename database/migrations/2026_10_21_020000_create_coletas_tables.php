<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD08 – Agendamento de coletas (RF27–RF32) e MOD09 – Coleta e recebimento de amostras (RF33–RF36).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coletas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos');
            $table->foreignId('coletor_id')->constrained('usuarios'); // RN29: usuário ativo com perfil Coletor

            // Agendamento (RF27, RN29)
            $table->date('data')->index();
            $table->string('periodo', 5); // MANHA ou TARDE
            $table->time('horario')->nullable();
            $table->text('instrucoes')->nullable();

            // RN31: AGENDADA, REALIZADA, NAO_REALIZADA ou CANCELADA.
            $table->string('situacao', 13)->default('AGENDADA')->index();
            $table->string('motivo', 255)->nullable(); // último reagendamento ou cancelamento (RF29)

            // Registro da coleta (RF33, RN32)
            $table->dateTime('data_hora_coleta')->nullable();
            $table->string('condicoes_tempo', 150)->nullable();
            $table->string('motivo_nao_realizada', 20)->nullable(); // mesmos motivos da amostra não coletada
            $table->string('motivo_nao_realizada_detalhe', 255)->nullable();
            $table->foreignId('registrada_por_id')->nullable()->constrained('usuarios');

            $table->foreignId('criado_por_id')->nullable()->constrained('usuarios');
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('amostras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coleta_id')->constrained('coletas');
            $table->foreignId('ponto_coleta_id')->constrained('pontos_coleta');

            // RN30: código único e sequencial por ano (AM-2026-00001), gerado no agendamento.
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('sequencia');
            $table->string('codigo', 15)->unique();

            // RN33: PREVISTA, COLETADA, NAO_COLETADA, CANCELADA, RECEBIDA ou REJEITADA.
            $table->string('situacao', 12)->default('PREVISTA')->index();

            // Coleta em campo (RF33)
            $table->decimal('temperatura_coleta', 4, 1)->nullable(); // °C
            $table->string('observacoes_coleta', 255)->nullable();
            $table->string('motivo_nao_coleta', 20)->nullable(); // PONTO_INACESSIVEL, SEM_AGUA, CLIENTE_AUSENTE ou OUTRO
            $table->foreignId('coletada_por_id')->nullable()->constrained('usuarios'); // quem registrou a coleta (RN34)

            // Recebimento no laboratório (RF34, RN35)
            $table->dateTime('recebida_em')->nullable();
            $table->foreignId('recebida_por_id')->nullable()->constrained('usuarios');
            $table->decimal('temperatura_recebimento', 4, 1)->nullable(); // °C
            $table->boolean('frasco_integro')->nullable();
            $table->boolean('identificacao_legivel')->nullable();
            $table->boolean('volume_suficiente')->nullable();
            $table->boolean('preservacao_adequada')->nullable();
            $table->boolean('fora_criterios')->default(false); // prazo ou temperatura fora do configurado
            $table->string('observacoes_recebimento', 255)->nullable();
            $table->string('motivo_rejeicao', 25)->nullable();

            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['ano', 'sequencia']);
        });

        // Itens do orçamento analisados em cada amostra; cada amostra consome 1 unidade de cada item (RN28).
        Schema::create('amostra_itens', function (Blueprint $table) {
            $table->foreignId('amostra_id')->constrained('amostras')->cascadeOnDelete();
            $table->foreignId('orcamento_item_id')->constrained('orcamento_itens');
            $table->primary(['amostra_id', 'orcamento_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amostra_itens');
        Schema::dropIfExists('amostras');
        Schema::dropIfExists('coletas');
    }
};
