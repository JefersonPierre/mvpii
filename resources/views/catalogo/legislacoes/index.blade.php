{{-- RF15 – Legislações de referência --}}
<x-layout titulo="Legislações">
    <x-abas-catalogo />

    <div class="flex flex-wrap items-end justify-between gap-2">
        <h1 class="text-2xl font-semibold">Legislações e limites</h1>
        <a href="{{ route('catalogo.legislacoes.create') }}" class="botao botao-primario">Nova legislação</a>
    </div>

    <div class="cartao">
        @if ($legislacoes->isEmpty())
            <p class="text-slate-500">Nenhuma legislação cadastrada.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Legislação</th><th>Órgão emissor</th><th>Tipos de amostra</th><th>Limites</th><th>Vigência</th></tr></thead>
                    <tbody>
                        @foreach ($legislacoes as $l)
                            <tr>
                                <td><a href="{{ route('catalogo.legislacoes.show', $l) }}" class="font-medium text-teal-800 hover:underline">{{ $l->nome }}</a></td>
                                <td>{{ $l->orgao_emissor }}</td>
                                <td>{{ $l->tiposAmostra->pluck('nome')->implode(', ') }}</td>
                                <td>{{ $l->limites_count }}</td>
                                <td><x-selo-vigencia :legislacao="$l" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layout>
