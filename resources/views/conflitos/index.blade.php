{{-- UC23 – Conflitos de sincronização (RF42, RN42) --}}
<x-layout titulo="Conflitos de sincronização">
    <div>
        <a href="{{ route('coletas.index') }}" class="text-sm text-teal-700 hover:underline">← Coletas</a>
        <h1 class="text-2xl font-semibold">Conflitos de sincronização</h1>
        <p class="text-sm text-slate-500">Registros feitos no celular que chegaram depois de a coleta mudar. A coleta não foi alterada: aceite ou descarte cada registro.</p>
    </div>

    <div class="flex gap-2 text-sm">
        <a href="{{ route('conflitos.index') }}" @class(['botao botao-pequeno', 'botao-primario' => $pendentes, 'botao-secundario' => ! $pendentes])>Pendentes</a>
        <a href="{{ route('conflitos.index', ['situacao' => 'resolvidos']) }}" @class(['botao botao-pequeno', 'botao-primario' => ! $pendentes, 'botao-secundario' => $pendentes])>Resolvidos</a>
    </div>

    <div class="cartao">
        @if ($conflitos->isEmpty())
            <p class="text-slate-500">{{ $pendentes ? 'Nenhum conflito pendente.' : 'Nenhum conflito resolvido.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Recebido em</th><th>Coletor</th><th>Cliente</th><th>Motivo do conflito</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($conflitos as $c)
                            <tr>
                                <td class="font-mono text-xs">{{ $c->recebida_em->format('d/m/Y H:i') }}</td>
                                <td>{{ $c->usuario->nome }}</td>
                                <td>{{ $c->coleta->orcamento->cliente->nome }}</td>
                                <td>{{ ucfirst($c->motivo_conflito) }}</td>
                                <td>{{ $c->nomeSituacao() }}{{ $c->resolvidaPor ? ' por '.$c->resolvidaPor->nome : '' }}</td>
                                <td class="text-right"><a href="{{ route('conflitos.show', $c) }}" class="botao botao-pequeno botao-secundario">Abrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $conflitos->links() }}
        @endif
    </div>
</x-layout>
