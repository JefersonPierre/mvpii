{{-- RN08: histórico do tipo de amostra --}}
<x-layout titulo="Histórico – {{ $tipo->nome }}">
    <div>
        <a href="{{ route('catalogo.tipos-amostra.index') }}" class="text-sm text-teal-700 hover:underline">← Tipos de amostra</a>
        <h1 class="text-2xl font-semibold">Histórico – {{ $tipo->nome }}</h1>
    </div>

    <div class="cartao space-y-4">
        <x-tabela-historico :registros="$registros" :campos="['nome' => 'Nome', 'descricao' => 'Descrição', 'ativo' => 'Situação']" />
    </div>
</x-layout>
