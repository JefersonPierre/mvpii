{{-- RN08: histórico de um registro do catálogo técnico --}}
<x-layout titulo="Histórico – {{ $titulo }}">
    <div>
        <a href="{{ $voltar[0] }}" class="text-sm text-teal-700 hover:underline">← {{ $voltar[1] }}</a>
        <h1 class="text-2xl font-semibold">Histórico – {{ $titulo }}</h1>
    </div>

    <div class="cartao space-y-4">
        <x-tabela-historico :registros="$registros" :campos="$campos" />
    </div>
</x-layout>
