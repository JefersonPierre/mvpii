{{-- UC26 – Emitir e enviar laudo (RF52, RF53, RN54, RN56), protótipo P18 --}}
<x-layout titulo="Laudos a emitir">
    <div>
        <a href="{{ route('laudos.index') }}" class="text-sm text-teal-700 hover:underline">← Laudos</a>
        <h1 class="text-2xl font-semibold">Laudos a emitir</h1>
        <p class="text-sm text-slate-500">Amostras com os resultados liberados, agrupadas por coleta. Os laudos da mesma coleta vão num único e-mail ao contato principal.</p>
    </div>

    @unless ($configurado)
        <div class="alerta alerta-erro">Configure o responsável técnico e o registro profissional em
            <a href="{{ route('configuracoes') }}#laboratorio" class="underline">Configurações</a> antes de emitir laudos.</div>
    @endunless
    @error('amostras') <div class="alerta alerta-erro">{{ $message }}</div> @enderror

    @if ($porColeta->isEmpty())
        <div class="cartao text-slate-500">Nenhuma amostra com resultados liberados.</div>
    @else
        <form method="POST" action="{{ route('laudos.store') }}" class="space-y-4">
            @csrf
            @foreach ($porColeta as $amostras)
                @php($coleta = $amostras->first()->coleta)
                @php($contato = $coleta->orcamento->cliente->contatoPrincipal)
                <fieldset class="cartao space-y-2">
                    <legend class="sr-only">Coleta de {{ $coleta->data->format('d/m/Y') }}</legend>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold">{{ $coleta->orcamento->cliente->nome }} · coleta de {{ ($coleta->data_hora_coleta ?? $coleta->data)->format('d/m/Y') }}</h2>
                        <span class="text-sm {{ $contato?->email ? 'text-slate-500' : 'text-amber-700' }}">
                            {{ $contato?->email ? 'Envio para '.$contato->email : 'Contato principal sem e-mail: entregue por outro meio' }}
                        </span>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($amostras as $a)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="amostras[]" value="{{ $a->id }}" checked>
                                    <span><span class="font-mono">{{ $a->codigo }}</span> · {{ $a->pontoColeta->identificacao }}</span>
                                    <x-selo-conformidade :valor="$a->conformidade" />
                                    @if ($a->laudoVigente)<span class="selo bg-amber-100 text-amber-800">Retificação de {{ $a->laudoVigente->numeroComRevisao() }}</span>@endif
                                </label>
                                <a href="{{ route('laudos.previa', $a) }}" target="_blank" class="botao botao-pequeno botao-secundario">Pré-visualizar</a>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
            @endforeach
            <div class="flex flex-wrap justify-end gap-2">
                <button type="submit" name="acao" value="emitir" class="botao botao-secundario" @disabled(! $configurado)>Emitir sem enviar</button>
                <button type="submit" name="acao" value="enviar" class="botao botao-primario" @disabled(! $configurado)>Emitir e enviar</button>
            </div>
        </form>
    @endif
</x-layout>
