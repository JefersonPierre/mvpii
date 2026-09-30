<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

// Dados iniciais: cria o primeiro usuário para permitir o acesso ao sistema.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('laboratorio.admin');

        Usuario::firstOrCreate(
            ['email' => mb_strtolower($admin['email'])],
            ['nome' => $admin['nome'], 'senha' => $admin['senha']],
        );
    }
}
