<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD01 – Acesso: usuários (RF01–RF03), links de criação de senha (RF02) e sessões.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('email', 150)->unique(); // RN11
            $table->string('senha');
            $table->unsignedTinyInteger('tentativas_falhas')->default(0); // RN09
            $table->timestamp('bloqueado_ate')->nullable(); // RN09
            $table->boolean('ativo')->default(true); // RN10
            $table->rememberToken();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        // Guarda só o hash do token; o token em si vai apenas no link do e-mail.
        Schema::create('tokens_senha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expira_em');
            $table->timestamp('usado_em')->nullable();
            $table->timestamp('criado_em')->nullable();
        });

        // Tabela padrão do Laravel para SESSION_DRIVER=database (o nome das colunas é fixo).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('tokens_senha');
        Schema::dropIfExists('usuarios');
    }
};
