<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// MOD07 – Perfis do usuário (RF25, RN23). Os perfis são fixos: ADMINISTRADOR, RECEPCAO, COLETOR e ANALISTA.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_perfis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('perfil', 15);
            $table->unique(['usuario_id', 'perfil']);
        });

        // RN25: na implantação, os usuários já cadastrados recebem o perfil Administrador.
        DB::table('usuario_perfis')->insertUsing(
            ['usuario_id', 'perfil'],
            DB::table('usuarios')->select('id', DB::raw("'ADMINISTRADOR'")),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_perfis');
    }
};
