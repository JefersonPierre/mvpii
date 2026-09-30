{{-- RF15 / RF16 – legislação e seus limites --}}
@use('App\Support\Numero')
@php($editavel = ! $legislacao->encerrada())

<x-layout :titulo="$legislacao->nome">
    <x-abas-catalogo />
    <a href="{{ route('catalogo.legislacoes.index') }}" class="text-sm text-teal-700 hover:underline">← Legislações</a>

    <div class="cartao space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold">{{ $legislacao->nome }}</h1>
                    <x-selo-vigencia :legislacao="$legislacao" />
                </div>
                <p class="text-sm text-slate-500">
                    {{ $legislacao->orgao_emissor }} · {{ $legislacao->tiposAmostra->pluck('nome')->implode(', ') }}
                </p>
                @if ($legislacao->substituidaPor)
                    <p class="text-sm">Substituída por
                        <a href="{{ route('catalogo.legislacoes.show', $legislacao->substituidaPor) }}" class="text-teal-700 underline">{{ $legislacao->substituidaPor->nome }}</a>.
                    </p>
                @endif
                @if ($substituiu)
                    <p class="text-sm">Substituiu
                        <a href="{{ route('catalogo.legislacoes.show', $substituiu) }}" class="text-teal-700 underline">{{ $substituiu->nome }}</a>.
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('catalogo.legislacoes.historico', $legislacao) }}" class="botao botao-secundario">Histórico</a>
                <a href="{{ route('catalogo.legislacoes.edit', $legislacao) }}" class="botao botao-secundario">Editar</a>
                @unless ($legislacao->substituida_por_id)
                    <a href="{{ route('catalogo.legislacoes.create', ['substitui' => $legislacao->id]) }}" class="botao botao-secundario">Substituir por nova</a>
                @endunless
            </div>
        </div>
    </div>

    <div class="cartao space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">Limites ({{ $limites->count() }})</h2>
            @if ($editavel)
                <a href="{{ route('catalogo.limites.create', $legislacao) }}" class="botao botao-primario">Adicionar limite</a>
            @endif
        </div>
        @unless ($editavel)
            <div class="alerta border-slate-200 bg-slate-50 text-slate-700">Legislação encerrada: os limites ficam apenas para consulta.</div>
        @endunless

        @if ($limites->isEmpty())
            <p class="text-slate-500">Nenhum limite cadastrado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[760px]">
                    <thead><tr><th>Parâmetro</th><th>Tipo de amostra</th><th>Tipo de limite</th><th class="text-right">Mínimo</th><th class="text-right">Máximo</th><th>Unidade</th><th>Observação</th>@if ($editavel)<th><span class="sr-only">Ações</span></th>@endif</tr></thead>
                    <tbody>
                        @foreach ($limites as $limite)
                            <tr>
                                <td class="font-medium">{{ $limite->parametro->nome }}</td>
                                <td>{{ $limite->tipoAmostra->nome }}</td>
                                <td>{{ $limite->nomeTipo() }}</td>
                                <td class="text-right font-mono">{{ Numero::decimal($limite->valor_minimo) ?: '—' }}</td>
                                <td class="text-right font-mono">{{ Numero::decimal($limite->valor_maximo) ?: '—' }}</td>
                                <td class="font-mono text-xs">{{ $limite->parametro->unidade }}</td>
                                <td>{{ $limite->observacao ?: '—' }}</td>
                                @if ($editavel)
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('catalogo.limites.edit', $limite) }}" class="botao botao-pequeno botao-secundario">Editar</a>
                                            <button type="button" class="botao botao-pequeno botao-perigo" data-abrir-dialogo="dialogo-remover"
                                                    data-acao="{{ route('catalogo.limites.destroy', $limite) }}"
                                                    data-nome="{{ $limite->parametro->nome }} / {{ $limite->tipoAmostra->nome }}">Remover</button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <dialog id="dialogo-remover" class="dialogo" aria-labelledby="titulo-remover">
        <form method="POST" class="space-y-4">
            @csrf
            @method('DELETE')
            <h2 id="titulo-remover" class="text-lg font-semibold">Remover limite</h2>
            <p>Remover o limite de <strong data-nome></strong>? A remoção fica registrada no histórico da legislação.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao botao-secundario" data-fechar-dialogo>Cancelar</button>
                <button type="submit" class="botao botao-perigo-cheio">Remover</button>
            </div>
        </form>
    </dialog>
</x-layout>
