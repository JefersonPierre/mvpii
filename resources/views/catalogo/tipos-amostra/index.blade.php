{{-- RF12 – Tipos de amostra --}}
<x-layout titulo="Tipos de amostra">
    <x-abas-catalogo />

    <div class="flex flex-wrap items-end justify-between gap-2">
        <h1 class="text-2xl font-semibold">Tipos de amostra</h1>
        @can('catalogo.editar')
            <a href="{{ route('catalogo.tipos-amostra.create') }}" class="botao botao-primario">Novo tipo de amostra</a>
        @endcan
    </div>

    <div class="cartao">
        @if ($tipos->isEmpty())
            <p class="text-slate-500">Nenhum tipo de amostra cadastrado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[640px]">
                    <thead><tr><th>Nome</th><th>Descrição</th><th>Pontos de coleta ativos</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
                    <tbody>
                        @foreach ($tipos as $tipo)
                            <tr>
                                <td class="font-medium">{{ $tipo->nome }}</td>
                                <td>{{ $tipo->descricao ?: '—' }}</td>
                                <td>{{ $tipo->pontos_coleta_count }}</td>
                                <td><x-selo-situacao :ativo="$tipo->ativo" /></td>
                                <td>
                                    <x-acoes-catalogo :editar="route('catalogo.tipos-amostra.edit', $tipo)" :historico="route('catalogo.tipos-amostra.historico', $tipo)"
                                                      :situacao="route('catalogo.tipos-amostra.situacao', $tipo)" :ativo="$tipo->ativo" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ajuda mt-3">Um tipo inativo não aparece em novos cadastros; os pontos que já o usam não mudam.</p>
        @endif
    </div>
</x-layout>
