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

            @if ($amostra->fotos->isNotEmpty())
                <div class="space-y-2">
                    <h2 class="font-semibold">Fotos da coleta</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($amostra->fotos as $foto)
                            <a href="{{ route('fotos.show', $foto) }}" target="_blank"><img src="{{ route('fotos.show', $foto) }}" alt="Foto da coleta" class="h-28 w-28 rounded border border-slate-200 object-cover"></a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- UC24–UC26: resultados, conformidade e laudos --}}
            @if ($amostra->resultados->contains(fn ($r) => $r->temResultado()) || in_array($amostra->situacao, Amostra::RECEBEM_RESULTADOS, true))
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold">Resultados</h2>
                        <div class="flex flex-wrap gap-2">
                            @if ($amostra->conformidade)<x-selo-conformidade :valor="$amostra->conformidade" />@endif
                            @if (in_array($amostra->situacao, Amostra::RECEBEM_RESULTADOS, true))
                                @can('resultados.lancar')<a href="{{ route('resultados.edit', $amostra) }}" class="botao botao-secundario">Lançar resultados</a>@endcan
                            @elseif ($amostra->situacao === Amostra::EM_REVISAO)
                                @can('resultados.liberar')<a href="{{ route('resultados.revisao', $amostra) }}" class="botao botao-secundario">Revisar e liberar</a>@endcan
                            @elseif ($amostra->situacao === Amostra::LIBERADA)
                                @can('laudos.emitir')<a href="{{ route('laudos.emitir') }}" class="botao botao-secundario">Emitir laudo</a>@endcan
                            @endif
                        </div>
                    </div>
                    @if ($amostra->legislacao)
                        <p class="text-sm text-slate-500">Legislação: {{ $amostra->legislacao->nome }}
                            @if ($amostra->liberada_em) · liberada em {{ $amostra->liberada_em->format('d/m/Y H:i') }} por {{ $amostra->liberadaPor?->nome }} @endif</p>
                    @endif
                    @if ($amostra->motivo_devolucao && $amostra->situacao === Amostra::EM_ANALISE)
                        <p class="text-sm text-amber-700">Devolvida para correção: {{ $amostra->motivo_devolucao }}</p>
                    @endif
                    @php($comResultado = $amostra->resultados->filter(fn ($r) => $r->temResultado()))
                    @if ($comResultado->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="tabela">
                                <thead><tr><th>Parâmetro</th><th>Resultado</th><th>Limite</th><th>Situação</th></tr></thead>
                                <tbody>
                                    @foreach ($comResultado as $r)
                                        <tr>
                                            <td>{{ $r->parametro->nome }} @if ($r->origem === \App\Models\Resultado::CAMPO)<span class="selo bg-slate-100 text-slate-600">Campo</span>@endif</td>
                                            <td>{{ $r->valorFormatado() }}</td>
                                            <td>{{ $r->limiteFormatado() ?? '–' }}</td>
                                            <td>{{ $r->nomeConformidade() ?? '–' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            @if ($amostra->laudos->isNotEmpty())
                <div class="space-y-2">
                    <h2 class="font-semibold">Laudos</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($amostra->laudos as $laudo)
                            <li>
                                @can('laudos.consultar')<a href="{{ route('laudos.show', $laudo) }}" class="font-mono text-teal-700 hover:underline">{{ $laudo->numeroComRevisao() }}</a>@else<span class="font-mono">{{ $laudo->numeroComRevisao() }}</span>@endcan
                                · {{ $laudo->emitido_em->format('d/m/Y H:i') }} · {{ $laudo->situacao === \App\Models\Laudo::VIGENTE ? 'Vigente' : 'Substituído' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
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
