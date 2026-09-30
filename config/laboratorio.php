<?php

// Configurações próprias do sistema do laboratório.
return [

    // Usuário inicial criado pelo seeder (php artisan db:seed).
    'admin' => [
        'nome' => env('ADMIN_NOME', 'Administrador'),
        'email' => env('ADMIN_EMAIL', 'admin@laboratorio.local'),
        'senha' => env('ADMIN_SENHA', 'Admin12345'),
    ],

];
