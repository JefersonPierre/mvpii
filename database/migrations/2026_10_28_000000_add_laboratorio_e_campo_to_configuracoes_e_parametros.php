<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RF43 / RN46 – parâmetros medidos em campo e RF56 – dados do laboratório usados no laudo e na rota (Entregável 3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametros', function (Blueprint $table) {
            $table->boolean('medido_em_campo')->default(false); // RN46: informado pelo coletor no registro da coleta
        });

        Schema::table('configuracoes', function (Blueprint $table) {
            $table->string('laboratorio_nome', 150)->nullable();
            $table->string('laboratorio_endereco', 255)->nullable();
            $table->string('laboratorio_telefone', 30)->nullable();
            $table->decimal('laboratorio_latitude', 10, 7)->nullable(); // ponto de partida das rotas (RN47)
            $table->decimal('laboratorio_longitude', 10, 7)->nullable();
            $table->string('responsavel_tecnico', 150)->nullable();
            $table->string('registro_profissional', 60)->nullable(); // ex.: CRQ IV 04123456
            $table->text('observacoes_laudo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes', function (Blueprint $table) {
            $table->dropColumn(['laboratorio_nome', 'laboratorio_endereco', 'laboratorio_telefone', 'laboratorio_latitude',
                'laboratorio_longitude', 'responsavel_tecnico', 'registro_profissional', 'observacoes_laudo']);
        });
        Schema::table('parametros', function (Blueprint $table) {
            $table->dropColumn('medido_em_campo');
        });
    }
};
