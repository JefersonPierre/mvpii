@props(['coletas'])

{{-- RF37: coletas e amostras na ficha do cliente e no orçamento. --}}
@if ($coletas->isEmpty())
    <p class="text-slate-500">Nenhuma coleta agendada.</p>
@else
    <div class="space-y-3">
        @foreach ($coletas as $coleta)
            <div class="rounded-md border border-slate-200 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <a href="{{ route('coletas.show', $coleta) }}" class="font-medium text-teal-800 hover:underline">{{ $coleta->quando() }}</a>
                    <span class="flex items-center gap-2 text-sm text-slate-500">
                        {{ $coleta->coletor->nome }}
                        @if (isset($coleta->orcamento) && Gate::allows('orcamentos'))
                            · <span class="font-mono">{{ $coleta->orcamento->numeroComRevisao() }}</span>
                        @endif
                        <x-selo-coleta :situacao="$coleta->situacao" />
                    </span>
                </div>
                <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    @foreach ($coleta->amostras as $amostra)
                        <li>
                            <a href="{{ route('amostras.show', $amostra) }}" class="font-mono text-teal-800 hover:underline">{{ $amostra->codigo }}</a>
                            {{ $amostra->pontoColeta->identificacao }}
                            <x-selo-amostra :situacao="$amostra->situacao" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
