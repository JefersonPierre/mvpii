{{-- MOD04 – Catálogo técnico --}}
@php
    $secoes = [
        ['Tipos de amostra', 'Água potável, efluente e outros tipos analisados pelo laboratório.', 'RF12', route('catalogo.tipos-amostra.index')],
        ['Parâmetros de análise', 'Unidade, método, limite de quantificação, categoria e preço.', 'RF13', route('catalogo.parametros.index')],
        ['Pacotes de análise', 'Conjuntos de parâmetros por tipo de amostra, com preço.', 'RF14', route('catalogo.pacotes.index')],
        ['Legislações e limites', 'Legislações de referência, vigência e limites por parâmetro.', 'RF15, RF16', route('catalogo.legislacoes.index')],
    ];
@endphp

<x-layout titulo="Catálogo técnico">
    <h1 class="text-2xl font-semibold">Catálogo técnico</h1>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($secoes as [$nome, $descricao, $requisito, $link])
            <div class="cartao flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ $nome }}</h2>
                    <span class="text-xs text-slate-400">{{ $requisito }}</span>
                </div>
                <p class="flex-1 text-sm text-slate-600">{{ $descricao }}</p>
                @if ($link)
                    <a href="{{ $link }}" class="botao botao-secundario self-start">Abrir</a>
                @else
                    <span class="text-sm text-slate-400">Em construção</span>
                @endif
            </div>
        @endforeach
    </div>
</x-layout>
