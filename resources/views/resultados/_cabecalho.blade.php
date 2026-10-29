{{-- Dados da amostra no lançamento e na revisão dos resultados. --}}
<div class="cartao grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
    <div><span class="text-slate-500">Cliente:</span> {{ $amostra->coleta->orcamento->cliente->nome }}</div>
    <div><span class="text-slate-500">Ponto:</span> {{ $amostra->pontoColeta->identificacao }}</div>
    <div><span class="text-slate-500">Coleta:</span> {{ $amostra->coleta->data_hora_coleta?->format('d/m/Y H:i') }}{{ $amostra->coleta->coletor ? ' · '.$amostra->coleta->coletor->nome : '' }}</div>
    <div><span class="text-slate-500">Recebida em:</span> {{ $amostra->recebida_em?->format('d/m/Y H:i') }}</div>
</div>
