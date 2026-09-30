{{-- RF12 – Tipos de amostra --}}
<x-layout titulo="Tipos de amostra">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <a href="{{ route('catalogo') }}" class="text-sm text-teal-700 hover:underline">← Catálogo técnico</a>
            <h1 class="text-2xl font-semibold">Tipos de amostra</h1>
        </div>
        <a href="{{ route('catalogo.tipos-amostra.create') }}" class="botao botao-primario">Novo tipo de amostra</a>
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
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('catalogo.tipos-amostra.edit', $tipo) }}" class="botao botao-pequeno botao-secundario">Editar</a>
                                        <a href="{{ route('catalogo.tipos-amostra.historico', $tipo) }}" class="botao botao-pequeno botao-secundario">Histórico</a>
                                        <form method="POST" action="{{ route('catalogo.tipos-amostra.situacao', $tipo) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="ativo" value="{{ $tipo->ativo ? 0 : 1 }}">
                                            @if ($tipo->ativo)
                                                <button type="submit" class="botao botao-pequeno botao-perigo">Inativar</button>
                                            @else
                                                <button type="submit" class="botao botao-pequeno bg-teal-50 text-teal-700 hover:bg-teal-100">Reativar</button>
                                            @endif
                                        </form>
                                    </div>
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
