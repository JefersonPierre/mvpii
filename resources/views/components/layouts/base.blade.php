@props(['titulo' => null])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ? $titulo.' – ' : '' }}Laboratório de Análise de Água</title>
    {{-- RF38: instalação no celular (PWA) --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f766e">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    {{ $slot }}
</body>
</html>
