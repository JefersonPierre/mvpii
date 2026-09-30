{{-- RF13 – Parâmetros de análise --}}
@use('App\Models\Parametro')
@use('App\Support\Numero')

<x-layout titulo="Parâmetros">
    <x-abas-catalogo />

    <div class="flex flex-wrap items-end justify-between gap-2">
        <h1 class="text-2xl font-semibold">Parâmetros de análise</h1>
        <a href="{{ route('catalogo.parametros.create') }}" class="botao botao-primario">Novo parâmetro</a>
    </div>

    <div class="cartao space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <label for="busca" class="rotulo">Nome</label>
                <input id="busca" name="busca" type="search" value="{{ $filtros['busca'] }}" class="campo">
            </div>
            <div>
                <label for="categoria" class="rotulo">Categoria</label>
                <select id="categoria" name="categoria" class="campo" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach (Parametro::CATEGORIAS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['categoria'] === $valor)>{{ $rotulo }}</option>
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

        @if ($parametros->isEmpty())
            <p class="text-slate-500">Nenhum parâmetro encontrado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Parâmetro</th><th>Unidade</th><th>Método</th><th>LQ</th><th>Categoria</th><th class="text-right">Preço</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
                    <tbody>
                        @foreach ($parametros as $p)
                            <tr>
                                <td class="font-medium">{{ $p->nome }}</td>
                                <td class="font-mono text-xs">{{ $p->unidade }}</td>
                                <td>{{ $p->metodo ?: '—' }}</td>
                                <td class="font-mono text-xs">{{ Numero::decimal($p->limite_quantificacao) ?: '—' }}</td>
                                <td>{{ $p->nomeCategoria() }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ Numero::moeda($p->preco) }}</td>
                                <td><x-selo-situacao :ativo="$p->ativo" /></td>
                                <td>
                                    <x-acoes-catalogo :editar="route('catalogo.parametros.edit', $p)" :historico="route('catalogo.parametros.historico', $p)"
                                                      :situacao="route('catalogo.parametros.situacao', $p)" :ativo="$p->ativo" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ajuda">LQ = limite de quantificação. Alterar o preço não muda orçamentos já emitidos.</p>
        @endif
    </div>
</x-layout>
