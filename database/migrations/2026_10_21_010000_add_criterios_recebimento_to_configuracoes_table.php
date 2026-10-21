<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RF35 – critérios de recebimento das amostras (RN35). Valores iniciais a confirmar com a gerente do laboratório.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->unsignedSmallInteger('prazo_recebimento_horas')->default(24); // da coleta ao recebimento
            $table->decimal('temperatura_maxima_recebimento', 4, 1)->default(10); // °C
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['prazo_recebimento_horas', 'temperatura_maxima_recebimento']);
        });
    }
};
