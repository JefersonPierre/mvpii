{{-- UC28 1a – Verificação pública do laudo (RF55, RN57): sem login e sem os resultados. --}}
@use('App\Services\EmissaoLaudos')

<x-layout-acesso titulo="Verificar laudo">
    <p class="text-sm text-slate-600">Informe o código de verificação impresso no laudo, ao lado do QR Code.</p>
    <form method="GET" action="{{ route('laudos.verificar') }}" class="flex gap-2">
        <label for="codigo" class="sr-only">Código de verificação</label>
        <input id="codigo" name="codigo" value="{{ $codigo ? EmissaoLaudos::formatarCodigo($codigo) : '' }}" placeholder="Ex.: 7KQ4-M2XP-9C"
               autocomplete="off" class="campo font-mono uppercase">
        <button type="submit" class="botao botao-primario">Verificar</button>
    </form>

    @if ($codigo !== '' && ! $laudo)
        <div class="alerta alerta-erro" role="alert">Laudo não encontrado. Confira o código digitado.</div>
    @elseif ($laudo)
        <div @class(['alerta', 'alerta-sucesso' => $laudo->vigente(), 'border-amber-200 bg-amber-50 text-amber-900' => ! $laudo->vigente()]) role="status">
            <p class="font-semibold">
                {{ $laudo->vigente() ? 'Laudo autêntico e vigente.' : 'Laudo autêntico, mas Substituído.' }}
            </p>
            @unless ($laudo->vigente())
                <p class="mt-1">Este laudo foi retificado{{ $laudo->substituidoPor ? ' pelo laudo '.$laudo->substituidoPor->numeroComRevisao() : '' }}. Peça ao laboratório o laudo vigente.</p>
            @endunless
        </div>
        <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1 text-sm">
            <dt class="text-slate-500">Número</dt><dd class="font-mono">{{ $laudo->numero() }}</dd>
            <dt class="text-slate-500">Revisão</dt><dd>{{ $laudo->revisao }}</dd>
            <dt class="text-slate-500">Emitido em</dt><dd>{{ $laudo->emitido_em->format('d/m/Y') }}</dd>
            <dt class="text-slate-500">Cliente</dt><dd>{{ $laudo->amostra->coleta->orcamento->cliente->nome }}</dd>
            <dt class="text-slate-500">Ponto de coleta</dt><dd>{{ $laudo->amostra->pontoColeta->identificacao }}</dd>
            <dt class="text-slate-500">Situação</dt><dd>{{ $laudo->nomeSituacao() }}</dd>
        </dl>
        <p class="ajuda">Os resultados constam apenas no laudo entregue ao cliente.</p>
    @endif
</x-layout-acesso>
