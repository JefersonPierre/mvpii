<?php

// Configurações próprias do sistema do laboratório.
return [

    // Usuário inicial criado pelo seeder (php artisan db:seed).
    'admin' => [
        'nome' => env('ADMIN_NOME', 'Administrador'),
        'email' => env('ADMIN_EMAIL', 'admin@laboratorio.local'),
        'senha' => env('ADMIN_SENHA', 'Admin12345'),
    ],

    // Dados do laboratório no cabeçalho do PDF e no e-mail do orçamento (RF18). Os vazios não aparecem.
    'dados' => [
        'nome' => env('LABORATORIO_NOME', 'Laboratório de Análise de Água'),
        'endereco' => env('LABORATORIO_ENDERECO'),
        'telefone' => env('LABORATORIO_TELEFONE'),
        'email' => env('LABORATORIO_EMAIL'),
        'cnpj' => env('LABORATORIO_CNPJ'),
    ],

    // UC03 3a: preenche o endereço pelo CEP usando o ViaCEP (serviço externo e opcional).
    'consulta_cep' => (bool) env('CEP_CONSULTA', true),

];
