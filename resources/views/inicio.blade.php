<x-layout titulo="Início">
    <h1 class="text-2xl font-semibold">Olá, {{ auth()->user()->nome }}</h1>
    <div class="cartao">
        <p class="mb-4">O atendimento começa pelo orçamento. Use os atalhos abaixo:</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('orcamentos') }}" class="botao botao-primario">Novo orçamento</a>
            <a href="{{ route('clientes') }}" class="botao botao-secundario">Pesquisar cliente</a>
        </div>
    </div>
</x-layout>
