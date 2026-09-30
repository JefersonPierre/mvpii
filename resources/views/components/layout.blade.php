@props(['titulo'])

@php
    $menu = [
        ['rotulo' => 'Início', 'rota' => 'inicio', 'ativo' => request()->routeIs('inicio')],
        ['rotulo' => 'Orçamentos', 'rota' => 'orcamentos.index', 'ativo' => request()->routeIs('orcamentos.*')],
        ['rotulo' => 'Clientes', 'rota' => 'clientes.index', 'ativo' => request()->routeIs('clientes.*', 'pontos.*')],
        ['rotulo' => 'Catálogo técnico', 'rota' => 'catalogo', 'ativo' => request()->routeIs('catalogo*')],
        ['rotulo' => 'Usuários', 'rota' => 'usuarios.index', 'ativo' => request()->routeIs('usuarios.*')],
        ['rotulo' => 'Configurações', 'rota' => 'configuracoes', 'ativo' => request()->routeIs('configuracoes*')],
    ];
@endphp

{{-- Moldura das telas internas: menu lateral + conteúdo. --}}
<x-layouts.base :titulo="$titulo">
    <div class="flex min-h-screen flex-col md:flex-row">
        <aside class="flex shrink-0 flex-col bg-slate-800 p-4 text-slate-200 md:w-60">
            <p class="mb-6 text-lg font-semibold text-white">Laboratório IQA</p>
            <nav class="flex flex-1 flex-wrap gap-1 md:flex-col">
                @foreach ($menu as $item)
                    <a href="{{ route($item['rota']) }}"
                       @class([
                           'rounded-md px-3 py-2 text-sm',
                           'bg-teal-600 text-white' => $item['ativo'],
                           'hover:bg-slate-700' => ! $item['ativo'],
                       ])
                       @if ($item['ativo']) aria-current="page" @endif>
                        {{ $item['rotulo'] }}
                    </a>
                @endforeach
            </nav>
            <div class="mt-6 flex items-center justify-between gap-2 border-t border-slate-700 pt-4">
                <span class="truncate text-sm text-slate-400">{{ auth()->user()->nome }}</span>
                <form method="POST" action="{{ route('sair') }}">
                    @csrf
                    <button type="submit" class="cursor-pointer text-sm text-slate-300 hover:text-white">Sair</button>
                </form>
            </div>
        </aside>

        <main class="flex-1 space-y-4 p-4 md:p-8">
            <x-mensagens />
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
