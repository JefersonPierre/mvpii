{{-- UC20 – Consultar roteiro de coletas (RF44–RF46, RN47, RN48) --}}
@use('App\Support\Numero')
@php
    $pontosMapa = $coletas->values()->map(function ($c, $i) {
        $p = $c->pontoNoMapa();

        return $p ? ['n' => $i + 1, 'lat' => $p->latitude, 'lng' => $p->longitude, 'situacao' => $c->situacao,
            'titulo' => $c->orcamento->cliente->nome, 'detalhe' => $p->identificacao] : null;
    })->filter()->values();
    $origem = $config->temCoordenadasLaboratorio() ? ['lat' => $config->laboratorio_latitude, 'lng' => $config->laboratorio_longitude,
        'titulo' => $config->dadosLaboratorio()['nome']] : null;
    $km = fn (?int $m) => $m === null ? null : ($m < 1000 ? $m.' m' : Numero::decimal(round($m / 1000, 1)).' km');
@endphp

<x-layout titulo="Roteiro de coletas">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('coletas.index') }}" class="text-sm text-teal-700 hover:underline">← Agenda</a>
            <h1 class="text-2xl font-semibold">Roteiro de coletas</h1>
            <p class="text-sm text-slate-500">Ordem de visita sugerida a partir do laboratório, pelo ponto mais próximo (RN47).</p>
        </div>
        <form method="GET" action="{{ route('coletas.roteiro') }}" class="flex flex-wrap items-end gap-2">
            @if (auth()->user()->veTodasAsColetas())
                <div>
                    <label for="coletor" class="rotulo">Coletor</label>
                    <select id="coletor" name="coletor" class="campo">
                        @foreach ($coletores as $c)
                            <option value="{{ $c->id }}" @selected($coletor?->id === $c->id)>{{ $c->nome }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="data" class="rotulo">Dia</label>
                <input id="data" name="data" type="date" value="{{ $data }}" class="campo">
            </div>
            <button type="submit" class="botao botao-secundario">Ver roteiro</button>
        </form>
    </div>

    @unless ($config->temCoordenadasLaboratorio())
        <div class="alerta alerta-info">As coordenadas do laboratório não estão configuradas: a ordem começa pela primeira coleta.
            @can('configuracoes') <a href="{{ route('configuracoes') }}#laboratorio" class="underline">Configurar</a>. @endcan</div>
    @endunless

    @if ($coletas->isEmpty())
        <div class="cartao text-slate-500">Nenhuma coleta {{ $coletor ? 'de '.$coletor->nome.' ' : '' }}em {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.</div>
    @else
        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div class="cartao space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ $coletas->count() }} {{ $coletas->count() === 1 ? 'coleta' : 'coletas' }} · {{ $coletor->nome }}</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($links['google'] as $i => $link)
                            <a href="{{ $link }}" target="_blank" rel="noopener" class="botao botao-pequeno botao-primario">
                                Google Maps{{ count($links['google']) > 1 ? ' – trecho '.($i + 1) : '' }}
                            </a>
                        @endforeach
                        @if ($links['waze'])
                            <a href="{{ $links['waze'] }}" target="_blank" rel="noopener" class="botao botao-pequeno botao-secundario">Waze (próximo ponto)</a>
                        @endif
                    </div>
                </div>
                @if (count($links['google']) > 1)
                    <p class="ajuda">O link do Google Maps leva até nove paradas: a rota foi dividida em trechos (RN48).</p>
                @endif
                <ol class="divide-y divide-slate-100">
                    @foreach ($coletas as $i => $c)
                        @php($ponto = $c->pontoNoMapa())
                        <li class="flex items-start gap-3 py-2.5">
                            <span @class(['mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white',
                                'bg-sky-600' => $c->situacao === 'AGENDADA', 'bg-green-600' => $c->situacao === 'REALIZADA', 'bg-amber-500' => $c->situacao === 'NAO_REALIZADA'])>{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('coletas.show', $c) }}" class="font-medium text-teal-800 hover:underline">{{ $c->orcamento->cliente->nome }}</a>
                                <div class="text-sm text-slate-600">{{ $c->amostras->map->pontoColeta->pluck('identificacao')->unique()->implode(' · ') }}</div>
                                <div class="text-xs text-slate-500">{{ $c->amostras->first()?->pontoColeta->enderecoCompleto() }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                    <x-selo-coleta :situacao="$c->situacao" />
                                    <span>{{ $c->nomePeriodo() }}{{ $c->horarioFormatado() ? ' · '.$c->horarioFormatado() : '' }}</span>
                                    @if ($ponto)
                                        @if ($distancias[$c->id] ?? null)<span class="text-slate-500">{{ $km($distancias[$c->id]) }} {{ $i === 0 ? 'do laboratório' : 'do ponto anterior' }}</span>@endif
                                    @else
                                        <span class="selo bg-amber-100 text-amber-800">Sem localização</span>
                                    @endif
                                    @if ($c->ordem_visita)<span class="text-slate-400">ordem alterada</span>@endif
                                </div>
                            </div>
                            @if ($podeOrdenar)
                                <div class="flex shrink-0 flex-col gap-1">
                                    @foreach (['subir' => '↑', 'descer' => '↓'] as $direcao => $seta)
                                        <form method="POST" action="{{ route('coletas.ordem', $c) }}">
                                            @csrf
                                            <input type="hidden" name="direcao" value="{{ $direcao }}">
                                            <button type="submit" class="botao botao-pequeno botao-secundario" aria-label="{{ $direcao === 'subir' ? 'Subir' : 'Descer' }} {{ $c->orcamento->cliente->nome }}"
                                                    @disabled(($direcao === 'subir' && $i === 0) || ($direcao === 'descer' && $i === $coletas->count() - 1))>{{ $seta }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="cartao p-2">
                <div data-mapa data-pontos='@json($pontosMapa)' data-origem='@json($origem)' class="h-[28rem] w-full rounded-md bg-slate-100"
                     role="img" aria-label="Mapa das coletas do dia"></div>
            </div>
        </div>
    @endif
</x-layout>
