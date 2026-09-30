<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RN08: histórico de inclusões, alterações e inativações (quem, quando, valor anterior e novo).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios'); // responsável
            $table->string('entidade', 50);
            $table->unsignedBigInteger('registro_id');
            $table->string('acao', 20); // INCLUSAO, ALTERACAO, INATIVACAO, REATIVACAO
            $table->string('campo', 50)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_novo')->nullable();
            $table->timestamp('data_hora')->useCurrent();

            $table->index(['entidade', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_auditoria');
    }
};
