{{-- MOD04 – Catálogo técnico --}}
@php
    $secoes = [
        ['Tipos de amostra', 'Água potável, efluente e outros tipos analisados pelo laboratório.', route('catalogo.tipos-amostra.index')],
        ['Parâmetros de análise', 'Unidade, método, limite de quantificação, categoria e preço.', route('catalogo.parametros.index')],
        ['Pacotes de análise', 'Conjuntos de parâmetros por tipo de amostra, com preço.', route('catalogo.pacotes.index')],
        ['Legislações e limites', 'Legislações de referência, vigência e limites por parâmetro.', route('catalogo.legislacoes.index')],
    ];
@endphp

<x-layout titulo="Catálogo técnico">
    <h1 class="text-2xl font-semibold">Catálogo técnico</h1>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($secoes as [$nome, $descricao, $link])
            <div class="cartao flex flex-col gap-2">
                <h2 class="text-lg font-semibold">{{ $nome }}</h2>
                <p class="flex-1 text-sm text-slate-600">{{ $descricao }}</p>
                <a href="{{ $link }}" class="botao botao-secundario self-start">Abrir</a>
            </div>
        @endforeach
    </div>
</x-layout>
