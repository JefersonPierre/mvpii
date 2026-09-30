<?php

namespace Database\Seeders;

use App\Models\TipoAmostra;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

// Dados iniciais: o primeiro usuário, para permitir o acesso, e os tipos de amostra do laboratório.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('laboratorio.admin');

        Usuario::firstOrCreate(
            ['email' => mb_strtolower($admin['email'])],
            ['nome' => $admin['nome'], 'senha' => $admin['senha']],
        );

        // RF12: o laboratório analisa água potável e efluentes.
        TipoAmostra::firstOrCreate(['nome' => 'Água potável'], ['descricao' => 'Água para consumo humano']);
        TipoAmostra::firstOrCreate(['nome' => 'Efluente'], ['descricao' => 'Efluentes domésticos e industriais']);

        // Catálogo de exemplo (preços fictícios): no ambiente local e na demonstração. Em produção, o laboratório cadastra o seu.
        if (app()->environment('local') || config('laboratorio.demonstracao')) {
            $this->call(CatalogoExemploSeeder::class);
        }
    }
}
