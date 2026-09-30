@props(['titulo'])

{{-- Moldura das telas de acesso (login, recuperar senha, nova senha). --}}
<x-layouts.base :titulo="$titulo">
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="cartao w-full max-w-sm space-y-4">
            <div>
                <p class="text-sm text-slate-500">Laboratório de Análise de Água</p>
                <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
            </div>
            <x-mensagens />
            {{ $slot }}
        </div>
    </main>
</x-layouts.base>
