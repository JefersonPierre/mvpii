{{-- UC25 – Revisar e liberar resultados (RF51, RN52, RN53), protótipo P18 --}}
@use('App\Models\Resultado')
@use('App\Support\Numero')

<x-layout titulo="Revisar resultados">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <a href="{{ route('resultados.index', ['aba' => 'revisar']) }}" class="text-sm text-teal-700 hover:underline">← A revisar</a>
            <h1 class="text-2xl font-semibold">Revisão da amostra <span class="font-mono">{{ $amostra->codigo }}</span></h1>
            <p class="text-sm text-slate-500">{{ $amostra->legislacao?->nome ?? 'Sem legislação de referência' }} · conclusão: <x-selo-conformidade :valor="$amostra->conformidade" /></p>
        </div>
        @can('laudos.emitir')
            <a href="{{ route('laudos.previa', $amostra) }}" target="_blank" class="botao botao-secundario">Pré-visualizar laudo</a>
        @endcan
    </div>
    @include('resultados._cabecalho')

    <div class="cartao space-y-3">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[900px]">
                <thead><tr><th>Parâmetro</th><th>Resultado</th><th>Limite</th><th>Conformidade</th><th>Análise</th><th>Lançado por</th><th>Observações</th></tr></thead>
                <tbody>
                    @foreach ($linhas as ['parametro' => $p, 'resultado' => $r])
                        <tr @class(['bg-red-50' => $r?->conformidade === 'NAO_CONFORME'])>
                            <td>{{ $p->nome }} <span class="text-xs text-slate-500">{{ $p->unidade }}</span></td>
                            <td class="font-mono">{{ $r?->valorFormatado() }}</td>
                            <td class="text-sm">{{ $amostra->legislacao ? $r?->limiteFormatado() : '—' }}</td>
                            <td><x-selo-conformidade :valor="$amostra->legislacao ? $r?->conformidade : null" /></td>
                            <td class="font-mono text-xs">{{ $r?->data_analise?->format('d/m/Y') }}</td>
                            <td class="text-sm">
                                {{ $r?->lancadoPor?->nome }}
                                @if ($r?->origem === Resultado::CAMPO)<span class="selo bg-teal-100 text-teal-800">Campo</span>@endif
                            </td>
                            <td class="text-sm">{{ $r?->observacoes }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($podeLiberar)
        <div class="cartao grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('resultados.liberar', $amostra) }}" class="space-y-2">
                @csrf
                <h2 class="font-semibold">Liberar resultados</h2>
                <p class="text-sm text-slate-600">Depois de liberados, os resultados não mudam, salvo por retificação do laudo.</p>
                <button type="submit" class="botao botao-primario">Liberar resultados</button>
            </form>
            <form method="POST" action="{{ route('resultados.devolver', $amostra) }}" class="space-y-2" novalidate>
                @csrf
                <h2 class="font-semibold">Devolver para correção</h2>
                <label for="motivo_devolucao" class="rotulo">Motivo *</label>
                <input id="motivo_devolucao" name="motivo_devolucao" maxlength="255" value="{{ old('motivo_devolucao') }}"
                       @class(['campo', 'campo-erro' => $errors->has('motivo_devolucao')])>
                @error('motivo_devolucao') <p class="erro">{{ $message }}</p> @enderror
                <button type="submit" class="botao botao-secundario">Devolver</button>
            </form>
        </div>
    @else
        <div class="alerta alerta-erro">Você lançou resultados desta amostra: a revisão e a liberação devem ser feitas por outro usuário (RN53).</div>
    @endif
    @error('amostra') <div class="alerta alerta-erro">{{ $message }}</div> @enderror
</x-layout>
