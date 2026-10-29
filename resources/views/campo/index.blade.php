{{-- MOD10 – Coletas do dia no celular (RF38–RF41, RF44–RF46; protótipos P14, P15 e P16). A tela é guardada pelo
     service worker e funciona sem conexão: os dados ficam no IndexedDB do aparelho (resources/js/campo.js). --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Coletas do dia – Laboratório de Análise de Água</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f766e">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/campo.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div id="campo" class="mx-auto flex min-h-screen max-w-xl flex-col"
         data-usuario-id="{{ auth()->id() }}" data-usuario-nome="{{ auth()->user()->nome }}"
         data-url-conectar="{{ route('campo.conectar') }}" data-url-sair="{{ route('sair') }}" data-url-login="{{ route('login') }}"
         data-url-inicio="{{ route('inicio') }}">
        <header class="sticky top-0 z-[1000] bg-teal-700 px-4 pt-[max(env(safe-area-inset-top),0.75rem)] pb-3 text-white shadow">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs text-teal-100">Laboratório IQA</p>
                    <h1 class="truncate text-lg font-semibold" data-titulo>Coletas do dia</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span data-conexao class="selo bg-white/20 text-white">…</span>
                    <button type="button" data-acao="menu" class="rounded-md px-2 py-1 text-2xl leading-none" aria-label="Menu">☰</button>
                </div>
            </div>
            <nav data-menu hidden class="mt-3 grid gap-1 text-sm">
                <a href="#/" class="rounded px-2 py-2 hover:bg-teal-600">Coletas do dia</a>
                <a href="#/envios" class="rounded px-2 py-2 hover:bg-teal-600">Envios <span data-contador-pendentes></span></a>
                <a href="{{ route('inicio') }}" class="rounded px-2 py-2 hover:bg-teal-600">Abrir o sistema completo</a>
                <button type="button" data-acao="sair" class="rounded px-2 py-2 text-left hover:bg-teal-600">Sair ({{ auth()->user()->nome }})</button>
            </nav>
        </header>
        <div data-aviso class="px-4 pt-3" aria-live="polite"></div>
        <main data-tela class="flex-1 space-y-3 p-4 pb-24">
            <p class="text-slate-500">Carregando…</p>
        </main>
        <footer class="fixed inset-x-0 bottom-0 z-[1000] mx-auto max-w-xl border-t border-slate-200 bg-white px-4 py-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] text-xs text-slate-500">
            <div class="flex items-center justify-between gap-2">
                <span data-ultima-sincronizacao>Ainda não sincronizado neste aparelho</span>
                <button type="button" data-acao="sincronizar" class="botao botao-pequeno botao-primario">Sincronizar</button>
            </div>
        </footer>
    </div>
</body>
</html>
