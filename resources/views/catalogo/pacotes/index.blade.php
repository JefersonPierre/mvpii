{{-- RF14 – Pacotes de análise --}}
@use('App\Support\Numero')

<x-layout titulo="Pacotes">
    <x-abas-catalogo />

    <div class="flex flex-wrap items-end justify-between gap-2">
        <h1 class="text-2xl font-semibold">Pacotes de análise</h1>
        <a href="{{ route('catalogo.pacotes.create') }}" class="botao botao-primario">Novo pacote</a>
    </div>

    <div class="cartao space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="tipo_amostra" class="rotulo">Tipo de amostra</label>
                <select id="tipo_amostra" name="tipo_amostra" class="campo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}" @selected($tipoAmostra === $tipo->id)>{{ $tipo->nome }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($pacotes->isEmpty())
            <p class="text-slate-500">Nenhum pacote cadastrado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Pacote</th><th>Tipo de amostra</th><th>Parâmetros</th><th class="text-right">Preço</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
                    <tbody>
                        @foreach ($pacotes as $pacote)
                            <tr>
                                <td class="font-medium">{{ $pacote->nome }}</td>
                                <td>{{ $pacote->tipoAmostra->nome }}</td>
                                <td class="max-w-md">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($pacote->parametros as $p)
                                            <span class="selo bg-slate-100 text-slate-700">{{ $p->nome }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-right font-mono whitespace-nowrap">{{ Numero::moeda($pacote->preco) }}</td>
                                <td><x-selo-situacao :ativo="$pacote->ativo" /></td>
                                <td>
                                    <x-acoes-catalogo :editar="route('catalogo.pacotes.edit', $pacote)" :historico="route('catalogo.pacotes.historico', $pacote)"
                                                      :situacao="route('catalogo.pacotes.situacao', $pacote)" :ativo="$pacote->ativo" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layout>
