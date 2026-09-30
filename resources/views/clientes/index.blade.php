{{-- UC07 – Consultar clientes (RF08) --}}
<x-layout titulo="Clientes">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <p class="text-sm text-slate-500">Pesquise, abra a ficha e acompanhe o histórico.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('clientes.create', ['cadastro' => 'interessado']) }}" class="botao botao-secundario">Cadastro rápido de interessado</a>
            <a href="{{ route('clientes.create') }}" class="botao botao-primario">Novo cliente</a>
        </div>
    </div>

    <div class="cartao space-y-4">
        <form method="GET" action="{{ route('clientes.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-56 flex-1">
                <label for="busca" class="rotulo">Nome, CPF ou CNPJ</label>
                <input id="busca" name="busca" type="search" value="{{ $filtros['busca'] }}" class="campo">
            </div>
            <div>
                <label for="cidade" class="rotulo">Cidade</label>
                <select id="cidade" name="cidade" class="campo" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach ($cidades as $cidade)
                        <option value="{{ $cidade }}" @selected($filtros['cidade'] === $cidade)>{{ $cidade }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="situacao" class="rotulo">Situação</label>
                <select id="situacao" name="situacao" class="campo" onchange="this.form.submit()">
                    @foreach (['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="botao botao-secundario">Pesquisar</button>
        </form>

        @if ($clientes->isEmpty())
            {{-- UC07 3a: nenhum resultado sugere cadastrar --}}
            <div class="rounded-md border border-dashed border-slate-300 p-6 text-center">
                <p class="mb-3 text-slate-600">Nenhum cliente encontrado.</p>
                <a href="{{ route('clientes.create') }}" class="botao botao-primario">Cadastrar novo cliente</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Cidade</th>
                            <th>Contato principal</th>
                            <th>Tipo</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $c)
                            <tr>
                                <td>
                                    <a href="{{ route('clientes.show', $c) }}" class="font-medium text-teal-800 hover:underline">{{ $c->nome }}</a>
                                    <div class="font-mono text-xs text-slate-500">{{ $c->documentoFormatado() ?: '—' }}</div>
                                </td>
                                <td>{{ $c->cidade ? $c->cidade.'/'.$c->uf : '—' }}</td>
                                <td>
                                    {{ $c->contatoPrincipal?->nome ?? '—' }}
                                    <div class="text-xs text-slate-500">{{ $c->contatoPrincipal?->telefoneFormatado() ?: $c->contatoPrincipal?->email }}</div>
                                </td>
                                <td><x-selo-cliente :cliente="$c" /></td>
                                <td><x-selo-situacao :ativo="$c->ativo" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $clientes->links() }}
        @endif
    </div>
</x-layout>
