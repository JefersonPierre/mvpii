<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD10 – Coleta em campo pelo celular (RF39–RF42): acesso por aparelho, registros recebidos e fotos.
return new class extends Migration
{
    public function up(): void
    {
        // RN39 / RNF16: token de acesso por aparelho, com validade, revogável pelo Administrador.
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->string('nome', 150); // navegador e sistema do aparelho
            $table->char('token_hash', 64)->unique(); // SHA-256 do token; o token só fica no aparelho
            $table->dateTime('expira_em');
            $table->dateTime('ultimo_uso_em')->nullable();
            $table->dateTime('revogado_em')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        // RN40–RN43: cada registro enviado pelo celular, com o identificador gerado no aparelho.
        Schema::create('sincronizacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // RN40: aceito uma única vez
            $table->foreignId('coleta_id')->constrained('coletas');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('dispositivo_id')->nullable()->constrained('dispositivos');
            $table->string('origem', 20); // CELULAR ou CELULAR_SEM_CONEXAO
            $table->json('dados'); // registro como chegou do aparelho (sem as fotos)
            // APLICADA, CONFLITO, ACEITA ou DESCARTADA
            $table->string('situacao', 10)->index();
            $table->string('motivo_conflito', 255)->nullable();
            $table->json('resposta')->nullable(); // devolvida de novo em caso de reenvio (RN40)
            $table->dateTime('recebida_em');
            $table->foreignId('resolvida_por_id')->nullable()->constrained('usuarios');
            $table->dateTime('resolvida_em')->nullable();
            $table->string('motivo_descarte', 255)->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        // RN45: até três fotos por amostra.
        Schema::create('fotos_amostra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amostra_id')->constrained('amostras');
            $table->foreignId('sincronizacao_id')->nullable()->constrained('sincronizacoes');
            $table->string('caminho', 255);
            $table->unsignedInteger('tamanho'); // bytes
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_amostra');
        Schema::dropIfExists('sincronizacoes');
        Schema::dropIfExists('dispositivos');
    }
};
