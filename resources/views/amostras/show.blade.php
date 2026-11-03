{{-- UC18 – amostra e sua linha do tempo (RF36, RNF12) --}}
@use('App\Models\Amostra')
@use('App\Support\Numero')
@php($coleta = $amostra->coleta)

<x-layout :titulo="$amostra->codigo">
    <a href="{{ route('amostras.index') }}" class="text-sm text-teal-700 hover:underline">← Amostras</a>

    <div class="grid items-start gap-4 lg:grid-cols-[1fr_24rem]">
        <div class="cartao space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-mono text-2xl font-semibold">{{ $amostra->codigo }}</h1>
                <x-selo-amostra :situacao="$amostra->situacao" />
            </div>
            <dl class="grid gap-x-8 gap-y-4 text-sm md:grid-cols-2">
                <div><dt class="text-slate-500">Cliente</dt><dd>{{ $coleta->orcamento->cliente->nome }}</dd></div>
                <div><dt class="text-slate-500">Ponto de coleta</dt><dd>{{ $amostra->pontoColeta->identificacao }}</dd></div>
                <div class="md:col-span-2"><dt class="text-slate-500">Itens a analisar</dt><dd>{{ $amostra->descricaoItens() }}</dd></div>
                <div>
                    <dt class="text-slate-500">Coleta</dt>
                    <dd><a href="{{ route('coletas.show', $coleta) }}" class="text-teal-700 hover:underline">{{ $coleta->quando() }}</a> · {{ $coleta->coletor->nome }}</dd>
                </div>
                @can('orcamentos')
                    <div>
                        <dt class="text-slate-500">Orçamento</dt>
                        <dd><a href="{{ route('orcamentos.show', $coleta->orcamento) }}" class="font-mono text-teal-700 hover:underline">{{ $coleta->orcamento->numeroComRevisao() }}</a></dd>
                    </div>
                @endcan
                @if ($coleta->data_hora_coleta && $amostra->situacao !== Amostra::NAO_COLETADA)
                    <div>
                        <dt class="text-slate-500">Coletada</dt>
                        <dd>
                            {{ $coleta->data_hora_coleta->format('d/m/Y H:i') }}
                            @if ($amostra->temperatura_coleta !== null) · {{ Numero::decimal($amostra->temperatura_coleta) }} °C @endif
                            @if ($amostra->observacoes_coleta) · {{ $amostra->observacoes_coleta }} @endif
                        </dd>
                    </div>
                @endif
                @if ($amostra->nomeMotivoNaoColeta())
                    <div><dt class="text-slate-500">Motivo da não coleta</dt><dd>{{ collect([$amostra->nomeMotivoNaoColeta(), $amostra->observacoes_coleta])->filter()->implode(' – ') }}</dd></div>
                @endif
                @if ($amostra->recebida_em)
                    <div>
                        <dt class="text-slate-500">{{ $amostra->situacao === Amostra::REJEITADA ? 'Rejeitada' : 'Recebida' }}</dt>
                        <dd>
                            {{ $amostra->recebida_em->format('d/m/Y H:i') }} por {{ $amostra->recebidaPor?->nome }} ·
                            {{ Numero::decimal($amostra->temperatura_recebimento) }} °C
                            @if ($amostra->fora_criterios) · <span class="font-medium text-amber-700">fora dos critérios</span> @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Condições do frasco</dt>
                        <dd>
                            @foreach (Amostra::CONDICOES_FRASCO as $campo => $nome)
                                <span @class(['mr-2', 'text-red-700' => ! $amostra->{$campo}])>{{ $amostra->{$campo} ? '✓' : '✗' }} {{ $nome }}</span>
                            @endforeach
                        </dd>
                    </div>
                    @if ($amostra->motivo_rejeicao || $amostra->observacoes_recebimento)
                        <div class="md:col-span-2">
                            <dt class="text-slate-500">{{ $amostra->motivo_rejeicao ? 'Motivo da rejeição' : 'Observação do recebimento' }}</dt>
                            <dd>{{ collect([$amostra->nomeMotivoRejeicao(), $amostra->observacoes_recebimento])->filter()->implode(' – ') }}</dd>
                        </div>
                    @endif
                @endif
            </dl>
        </div>

        {{-- RF36 / RNF12: cada etapa com usuário, data e hora --}}
        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Linha do tempo</h2>
            <ol class="space-y-4 border-l-2 border-slate-200 pl-5">
                @foreach ($etapas as $etapa)
                    <li class="relative">
                        <span class="absolute top-1.5 -left-[27px] h-3 w-3 rounded-full bg-teal-700"></span>
                        <p><strong>{{ $etapa['titulo'] }}</strong> · {{ $etapa['data_hora']->format('d/m/Y H:i') }} · {{ $etapa['usuario'] }}</p>
                        @foreach ($etapa['detalhes'] as $detalhe)
                            <p class="text-sm text-slate-500">{{ $detalhe }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</x-layout>
