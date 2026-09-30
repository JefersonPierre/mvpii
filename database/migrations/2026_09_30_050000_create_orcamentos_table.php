<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MOD05 – Orçamentos (RF17–RF21).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            // RN12: número sequencial por ano (ORC-2026-0001); cada revisão mantém o número e incrementa a revisão.
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('sequencia');
            $table->unsignedSmallInteger('revisao')->default(1);
            $table->foreignId('substitui_id')->nullable()->constrained('orcamentos'); // revisão anterior (RN16)

            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('contato_id')->nullable()->constrained('contatos')->nullOnDelete(); // destinatário
            $table->foreignId('tipo_amostra_id')->constrained('tipos_amostra');

            // Valores do orçamento (RN13); os padrões vêm das configurações (RF22).
            $table->decimal('subtotal_itens', 12, 2)->default(0);
            $table->decimal('taxa_coleta', 10, 2)->default(0);
            $table->decimal('desconto_percentual', 5, 2)->default(0); // RN14
            $table->decimal('valor_desconto', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->unsignedSmallInteger('validade_dias');
            $table->text('condicoes_pagamento')->nullable();
            $table->text('observacoes')->nullable();

            // RF19: RASCUNHO, ENVIADO, APROVADO, RECUSADO, EXPIRADO ou SUBSTITUIDO.
            $table->string('situacao', 12)->default('RASCUNHO')->index();
            $table->dateTime('data_envio')->nullable();
            $table->date('valido_ate')->nullable(); // data do envio + validade (RN17)
            $table->string('envio_email', 150)->nullable(); // vazio = envio registrado manualmente (UC09 2a)
            $table->string('pdf_caminho', 255)->nullable();
            $table->date('data_resposta')->nullable();
            $table->string('motivo_recusa', 20)->nullable(); // RN19: PRECO, PRAZO, DESISTENCIA ou OUTRO
            $table->string('motivo_detalhe', 255)->nullable();

            $table->foreignId('criado_por_id')->nullable()->constrained('usuarios');
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['ano', 'sequencia', 'revisao']);
        });

        // RN15: nome e preço são copiados do catálogo; mudanças posteriores no catálogo não afetam o orçamento.
        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->cascadeOnDelete();
            $table->string('tipo', 10); // PACOTE ou PARAMETRO
            $table->foreignId('pacote_id')->nullable()->constrained('pacotes');
            $table->foreignId('parametro_id')->nullable()->constrained('parametros');
            $table->string('descricao', 150);
            $table->text('parametros_incluidos')->nullable(); // pacote: nomes dos parâmetros, para o PDF
            $table->decimal('preco_unitario', 10, 2);
            $table->unsignedSmallInteger('quantidade'); // quantidade de amostras
            $table->decimal('subtotal', 12, 2);
            $table->unsignedSmallInteger('ordem')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_itens');
        Schema::dropIfExists('orcamentos');
    }
};
