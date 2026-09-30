{{-- RN08: histórico de alterações do usuário (quem, quando, valor anterior e novo) --}}
<x-layout titulo="Histórico – {{ $usuario->nome }}">
    <div>
        <a href="{{ route('usuarios.index') }}" class="text-sm text-teal-700 hover:underline">← Usuários</a>
        <h1 class="text-2xl font-semibold">Histórico – {{ $usuario->nome }}</h1>
    </div>

    <div class="cartao space-y-4">
        <x-tabela-historico :registros="$registros" :campos="['nome' => 'Nome', 'email' => 'E-mail', 'ativo' => 'Situação']" />
    </div>
</x-layout>
