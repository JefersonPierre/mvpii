@props(['titulo' => null])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ? $titulo.' – ' : '' }}Laboratório de Análise de Água</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    @if (config('laboratorio.demonstracao'))
        <p class="bg-amber-100 px-4 py-1 text-center text-sm text-amber-900">
            Ambiente de demonstração: os e-mails são simulados e nenhuma mensagem é enviada de verdade.
        </p>
    @endif
    {{ $slot }}
</body>
</html>
