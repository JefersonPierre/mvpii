{{-- UC11 – Consultar orçamentos (RF21) --}}
@use('App\Models\Orcamento')
@use('App\Support\Numero')

<x-layout titulo="Orçamentos">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">Orçamentos</h1>
            <p class="text-sm text-slate-500">Acompanhe a situação e registre a resposta do cliente.</p>
        </div>
        <a href="{{ route('orcamentos.create') }}" class="botao botao-primario">Novo orçamento</a>
    </div>

    <div class="cartao space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <label for="numero" class="rotulo">Número</label>
                <input id="numero" name="numero" value="{{ $filtros['numero'] }}" placeholder="ORC-2026-0001" class="campo font-mono">
            </div>
            <div class="min-w-48 flex-1">
                <label for="cliente" class="rotulo">Cliente</label>
                <input id="cliente" name="cliente" type="search" value="{{ $filtros['cliente'] }}" class="campo">
            </div>
            <div>
                <label for="de" class="rotulo">Emitido de</label>
                <input id="de" name="de" type="date" value="{{ $filtros['de'] }}" class="campo">
            </div>
            <div>
                <label for="ate" class="rotulo">até</label>
                <input id="ate" name="ate" type="date" value="{{ $filtros['ate'] }}" class="campo">
            </div>
            <div>
                <label for="situacao" class="rotulo">Situação</label>
                <select id="situacao" name="situacao" class="campo" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach (Orcamento::SITUACOES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="botao botao-secundario">Pesquisar</button>
        </form>

        @if ($orcamentos->isEmpty())
            {{-- UC11 3a --}}
            <div class="rounded-md border border-dashed border-slate-300 p-6 text-center">
                <p class="mb-3 text-slate-600">Nenhum orçamento encontrado.</p>
                <a href="{{ route('orcamentos.create') }}" class="botao botao-primario">Criar novo orçamento</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Número</th><th>Cliente</th><th>Emissão</th><th>Validade</th><th class="text-right">Valor</th><th>Situação</th></tr></thead>
                    <tbody>
                        @foreach ($orcamentos as $o)
                            <tr>
                                <td><a href="{{ route('orcamentos.show', $o) }}" class="font-mono font-medium text-teal-800 hover:underline">{{ $o->numeroComRevisao() }}</a></td>
                                <td>{{ $o->cliente->nome }}</td>
                                <td class="font-mono text-xs">{{ $o->criado_em->format('d/m/Y') }}</td>
                                <td class="font-mono text-xs">{{ $o->valido_ate?->format('d/m/Y') ?? $o->validade_dias.' dias' }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ Numero::moeda($o->valor_total) }}</td>
                                <td><x-selo-orcamento :situacao="$o->situacao" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orcamentos->links() }}
        @endif
    </div>
</x-layout>
