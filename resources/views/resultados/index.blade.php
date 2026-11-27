{{-- UC24 / UC25 – Resultados: amostras a lançar e a revisar (RF48, RF51, RN53) --}}
<x-layout titulo="Resultados">
    <div>
        <h1 class="text-2xl font-semibold">Resultados</h1>
        <p class="text-sm text-slate-500">Lance os resultados das amostras recebidas e revise os lançados por outro usuário.</p>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('resultados.index') }}" @class(['botao', 'botao-primario' => $aba === 'lancar', 'botao-secundario' => $aba !== 'lancar'])>A lançar ({{ $aLancar->count() }})</a>
        <a href="{{ route('resultados.index', ['aba' => 'revisar']) }}" @class(['botao', 'botao-primario' => $aba === 'revisar', 'botao-secundario' => $aba !== 'revisar'])>A revisar ({{ $aRevisar->count() }})</a>
    </div>

    <div class="cartao space-y-3">
        @if ($aba === 'revisar')
            <p class="text-sm text-slate-500">Só aparecem as amostras em que você não lançou nenhum resultado (separação de funções, RN53).</p>
        @endif
        @php($lista = $aba === 'revisar' ? $aRevisar : $aLancar)
        @if ($lista->isEmpty())
            <p class="text-slate-500">{{ $aba === 'revisar' ? 'Nenhuma amostra para você revisar.' : 'Nenhuma amostra aguardando resultados.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[720px]">
                    <thead><tr><th>Amostra</th><th>Cliente</th><th>Ponto de coleta</th><th>Recebida em</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($lista as $a)
                            <tr>
                                <td class="font-mono">{{ $a->codigo }}</td>
                                <td>{{ $a->coleta->orcamento->cliente->nome }}</td>
                                <td>{{ $a->pontoColeta->identificacao }}</td>
                                <td class="font-mono text-xs">{{ $a->recebida_em?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <x-selo-amostra :situacao="$a->situacao" />
                                    @if ($a->motivo_devolucao)<span class="block text-xs text-amber-700">Devolvida: {{ $a->motivo_devolucao }}</span>@endif
                                    @if ($a->motivo_retificacao)<span class="block text-xs text-amber-700">Retificação: {{ $a->motivo_retificacao }}</span>@endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ $aba === 'revisar' ? route('resultados.revisao', $a) : route('resultados.edit', $a) }}" class="botao botao-pequeno botao-primario">
                                        {{ $aba === 'revisar' ? 'Revisar' : 'Lançar resultados' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layout>
