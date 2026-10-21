{{-- UC18 – Consultar amostras (RF36, RN36) --}}
@use('App\Models\Amostra')

<x-layout titulo="Amostras">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">Amostras</h1>
            <p class="text-sm text-slate-500">Pesquise e acompanhe cada amostra do agendamento ao recebimento.</p>
        </div>
        @can('amostras.receber')
            <a href="{{ route('amostras.receber') }}" class="botao botao-primario">Receber amostras</a>
        @endcan
    </div>

    <div class="cartao space-y-4">
        <form method="GET" action="{{ route('amostras.index') }}" class="grid gap-3 md:grid-cols-3 xl:grid-cols-[1fr_1.3fr_1.3fr_9.5rem_9.5rem_10rem_auto] xl:items-end">
            <div>
                <label for="codigo" class="rotulo">Código</label>
                <input id="codigo" name="codigo" type="search" value="{{ $filtros['codigo'] }}" placeholder="AM-…" class="campo font-mono">
            </div>
            <div>
                <label for="cliente" class="rotulo">Cliente</label>
                <input id="cliente" name="cliente" type="search" value="{{ $filtros['cliente'] }}" class="campo">
            </div>
            <div>
                <label for="ponto" class="rotulo">Ponto de coleta</label>
                <input id="ponto" name="ponto" type="search" value="{{ $filtros['ponto'] }}" class="campo">
            </div>
            <div>
                <label for="de" class="rotulo">Coleta de</label>
                <input id="de" name="de" type="date" value="{{ $filtros['de'] }}" class="campo">
            </div>
            <div>
                <label for="ate" class="rotulo">até</label>
                <input id="ate" name="ate" type="date" value="{{ $filtros['ate'] }}" class="campo">
            </div>
            <div>
                <label for="situacao" class="rotulo">Situação</label>
                <select id="situacao" name="situacao" class="campo">
                    <option value="">Todas</option>
                    @foreach (Amostra::SITUACOES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="botao botao-secundario">Pesquisar</button>
        </form>

        @if ($amostras->isEmpty())
            <p class="text-slate-500">Nenhuma amostra encontrada.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Código</th><th>Cliente</th><th>Ponto de coleta</th><th>Coleta</th><th>Situação</th></tr></thead>
                    <tbody>
                        @foreach ($amostras as $a)
                            <tr>
                                <td><a href="{{ route('amostras.show', $a) }}" class="font-mono text-teal-800 hover:underline">{{ $a->codigo }}</a></td>
                                <td>{{ $a->coleta->orcamento->cliente->nome }}</td>
                                <td>{{ $a->pontoColeta->identificacao }}</td>
                                <td class="font-mono text-xs">
                                    {{ $a->coleta->data_hora_coleta?->format('d/m/Y H:i') ?? $a->coleta->data->format('d/m/Y').' '.mb_strtolower($a->coleta->nomePeriodo()) }}
                                </td>
                                <td><x-selo-amostra :situacao="$a->situacao" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $amostras->links() }}
        @endif
    </div>
</x-layout>
