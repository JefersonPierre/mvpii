<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD02 – Clientes (RF04–RF08): clientes, interessados e seus contatos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->boolean('interessado')->default(false); // RF05: cadastro rápido, completado na aprovação do orçamento
            $table->char('tipo_pessoa', 1); // F = física, J = jurídica
            $table->string('nome', 150); // nome ou razão social
            $table->string('nome_fantasia', 150)->nullable();
            $table->string('documento', 14)->nullable()->unique(); // RN01: CPF ou CNPJ sem máscara
            $table->char('cep', 8)->nullable();
            $table->string('logradouro', 150)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80)->nullable()->index();
            $table->char('uf', 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true); // RN04: não é excluído, só inativado
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index('nome');
        });

        // RF06 / RN02: ao menos um contato com telefone ou e-mail, e só um principal.
        Schema::create('contatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('cargo', 80)->nullable();
            $table->string('telefone', 11)->nullable(); // só dígitos, com DDD
            $table->string('email', 150)->nullable();
            $table->boolean('principal')->default(false);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos');
        Schema::dropIfExists('clientes');
    }
};
