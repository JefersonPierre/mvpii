@use('App\Support\Numero')

<x-layout titulo="Início">
    <h1 class="text-2xl font-semibold">Olá, {{ auth()->user()->nome }}</h1>
    <div class="cartao">
        <p class="mb-4">O atendimento começa pelo orçamento. Use os atalhos abaixo:</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('orcamentos.create') }}" class="botao botao-primario">Novo orçamento</a>
            <a href="{{ route('clientes.index') }}" class="botao botao-secundario">Pesquisar cliente</a>
        </div>
    </div>

    <div class="grid items-start gap-4 lg:grid-cols-2">
        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Aguardando resposta do cliente ({{ $aguardando->count() }})</h2>
            @forelse ($aguardando as $o)
                @php($dias = (int) today()->diffInDays($o->valido_ate, false))
                <a href="{{ route('orcamentos.show', $o) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                    <span>
                        <span class="font-mono text-sm text-teal-800">{{ $o->numeroComRevisao() }}</span>
                        <span class="block text-sm">{{ $o->cliente->nome }}</span>
                    </span>
                    <span class="text-right text-sm whitespace-nowrap">
                        <span class="font-mono">{{ Numero::moeda($o->valor_total) }}</span>
                        <span @class(['block text-xs', 'font-semibold text-amber-700' => $dias <= 3, 'text-slate-500' => $dias > 3])>
                            {{ $dias === 0 ? 'vence hoje' : ($dias === 1 ? 'vence amanhã' : "vence em {$dias} dias") }}
                        </span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-500">Nenhum orçamento aguardando resposta.</p>
            @endforelse
        </div>

        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Rascunhos ({{ $rascunhos->count() }})</h2>
            @forelse ($rascunhos as $o)
                <a href="{{ route('orcamentos.show', $o) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                    <span>
                        <span class="font-mono text-sm text-teal-800">{{ $o->numeroComRevisao() }}</span>
                        <span class="block text-sm">{{ $o->cliente->nome }}</span>
                    </span>
                    <span class="text-right text-sm whitespace-nowrap">
                        <span class="font-mono">{{ Numero::moeda($o->valor_total) }}</span>
                        <span class="block text-xs text-slate-500">alterado em {{ $o->atualizado_em->format('d/m') }}</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-500">Nenhum rascunho pendente.</p>
            @endforelse
        </div>
    </div>
</x-layout>
