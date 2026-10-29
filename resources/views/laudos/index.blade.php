{{-- UC28 – Consultar laudos e não conformidades (RF57), protótipo P19 --}}
@use('App\Models\Amostra')
@use('App\Models\Laudo')

<x-layout titulo="Laudos">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">Laudos</h1>
            <p class="text-sm text-slate-500">Pesquise os laudos emitidos e as amostras não conformes.</p>
        </div>
        <div class="flex gap-2">
            @can('laudos.emitir')<a href="{{ route('laudos.emitir') }}" class="botao botao-primario">Laudos a emitir</a>@endcan
            <a href="{{ route('laudos.verificar') }}" class="botao botao-secundario" target="_blank">Página de verificação</a>
        </div>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('laudos.index') }}" @class(['botao', 'botao-primario' => $aba === 'laudos', 'botao-secundario' => $aba !== 'laudos'])>Laudos</a>
        <a href="{{ route('laudos.index', ['aba' => 'nao-conformes']) }}" @class(['botao', 'botao-primario' => $aba !== 'laudos', 'botao-secundario' => $aba === 'laudos'])>Amostras não conformes</a>
    </div>

    <div class="cartao space-y-4">
        <form method="GET" action="{{ route('laudos.index') }}" class="grid gap-3 md:grid-cols-3 xl:grid-cols-[9rem_1.3fr_1.3fr_9.5rem_9.5rem_10rem_9rem_auto] xl:items-end">
            @if ($aba !== 'laudos')<input type="hidden" name="aba" value="nao-conformes">@endif
            @if ($aba === 'laudos')
                <div>
                    <label for="numero" class="rotulo">Número</label>
                    <input id="numero" name="numero" type="search" value="{{ $filtros['numero'] }}" placeholder="LAU-…" class="campo font-mono">
                </div>
            @endif
            <div>
                <label for="cliente" class="rotulo">Cliente</label>
                <input id="cliente" name="cliente" type="search" value="{{ $filtros['cliente'] }}" class="campo">
            </div>
            <div>
                <label for="ponto" class="rotulo">Ponto de coleta</label>
                <input id="ponto" name="ponto" type="search" value="{{ $filtros['ponto'] }}" class="campo">
            </div>
            <div>
                <label for="de" class="rotulo">{{ $aba === 'laudos' ? 'Emitido de' : 'Coleta de' }}</label>
                <input id="de" name="de" type="date" value="{{ $filtros['de'] }}" class="campo">
            </div>
            <div>
                <label for="ate" class="rotulo">até</label>
                <input id="ate" name="ate" type="date" value="{{ $filtros['ate'] }}" class="campo">
            </div>
            @if ($aba === 'laudos')
                <div>
                    <label for="conclusao" class="rotulo">Conclusão</label>
                    <select id="conclusao" name="conclusao" class="campo">
                        <option value="">Todas</option>
                        @foreach (Amostra::CONFORMIDADES as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filtros['conclusao'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="situacao" class="rotulo">Situação</label>
                    <select id="situacao" name="situacao" class="campo">
                        <option value="">Todas</option>
                        @foreach (Laudo::SITUACOES as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="botao botao-secundario">Pesquisar</button>
        </form>

        @if ($itens->isEmpty())
            <p class="text-slate-500">{{ $aba === 'laudos' ? 'Nenhum laudo encontrado.' : 'Nenhuma amostra não conforme encontrada.' }}</p>
        @elseif ($aba === 'laudos')
            <div class="overflow-x-auto">
                <table class="tabela min-w-[860px]">
                    <thead><tr><th>Número</th><th>Amostra</th><th>Cliente</th><th>Ponto</th><th>Emitido em</th><th>Conclusão</th><th>Situação</th></tr></thead>
                    <tbody>
                        @foreach ($itens as $l)
                            <tr>
                                <td><a href="{{ route('laudos.show', $l) }}" class="font-mono text-teal-800 hover:underline">{{ $l->numeroComRevisao() }}</a></td>
                                <td class="font-mono text-xs">{{ $l->amostra->codigo }}</td>
                                <td>{{ $l->amostra->coleta->orcamento->cliente->nome }}</td>
                                <td>{{ $l->amostra->pontoColeta->identificacao }}</td>
                                <td class="font-mono text-xs">{{ $l->emitido_em->format('d/m/Y') }}</td>
                                <td><x-selo-conformidade :valor="$l->conclusao" /></td>
                                <td><span @class(['selo', 'bg-teal-100 text-teal-800' => $l->vigente(), 'bg-slate-200 text-slate-600' => ! $l->vigente()])>{{ $l->nomeSituacao() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $itens->links() }}
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[860px]">
                    <thead><tr><th>Amostra</th><th>Cliente</th><th>Ponto</th><th>Coleta</th><th>Parâmetros não conformes</th><th>Laudo</th></tr></thead>
                    <tbody>
                        @foreach ($itens as $a)
                            <tr>
                                <td><a href="{{ route('amostras.show', $a) }}" class="font-mono text-teal-800 hover:underline">{{ $a->codigo }}</a></td>
                                <td>{{ $a->coleta->orcamento->cliente->nome }}</td>
                                <td>{{ $a->pontoColeta->identificacao }}</td>
                                <td class="font-mono text-xs">{{ $a->coleta->data_hora_coleta?->format('d/m/Y') }}</td>
                                <td class="text-sm text-red-700">{{ $a->resultados->map(fn ($r) => $r->parametro->nome.' ('.$r->valorFormatado().')')->implode(', ') }}</td>
                                <td>
                                    @if ($a->laudoVigente)<a href="{{ route('laudos.show', $a->laudoVigente) }}" class="font-mono text-xs text-teal-800 hover:underline">{{ $a->laudoVigente->numeroComRevisao() }}</a>
                                    @else <x-selo-amostra :situacao="$a->situacao" /> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $itens->links() }}
        @endif
    </div>
</x-layout>
