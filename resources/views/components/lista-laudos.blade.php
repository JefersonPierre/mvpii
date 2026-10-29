@props(['laudos'])

{{-- UC28 (1c): laudos de um cliente ou orçamento --}}
@if ($laudos->isEmpty())
    <p class="text-slate-500">Nenhum laudo emitido.</p>
@else
    <div class="overflow-x-auto">
        <table class="tabela min-w-[640px]">
            <thead><tr><th>Número</th><th>Amostra</th><th>Ponto</th><th>Emitido em</th><th>Conclusão</th><th>Situação</th></tr></thead>
            <tbody>
                @foreach ($laudos as $l)
                    <tr>
                        <td><a href="{{ route('laudos.show', $l) }}" class="font-mono text-teal-800 hover:underline">{{ $l->numeroComRevisao() }}</a></td>
                        <td><a href="{{ route('amostras.show', $l->amostra) }}" class="font-mono text-xs text-teal-800 hover:underline">{{ $l->amostra->codigo }}</a></td>
                        <td>{{ $l->amostra->pontoColeta->identificacao }}</td>
                        <td class="font-mono text-xs">{{ $l->emitido_em->format('d/m/Y') }}</td>
                        <td><x-selo-conformidade :valor="$l->conclusao" /></td>
                        <td><span @class(['selo', 'bg-teal-100 text-teal-800' => $l->vigente(), 'bg-slate-200 text-slate-600' => ! $l->vigente()])>{{ $l->nomeSituacao() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
