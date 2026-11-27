{{-- UC23 – Resolver conflito de sincronização (RF42, RN43): coleta atual e registro recebido lado a lado (P16). --}}
@use('App\Models\Amostra')
@use('App\Models\Coleta')
@use('App\Support\Numero')
@php
    $coleta = $conflito->coleta;
    $dados = $conflito->dados;
    $amostras = $coleta->amostras->keyBy('id');
    $fotos = $conflito->fotos->groupBy('amostra_id');
@endphp

<x-layout titulo="Conflito de sincronização">
    <div>
        <a href="{{ route('conflitos.index') }}" class="text-sm text-teal-700 hover:underline">← Conflitos</a>
        <h1 class="text-2xl font-semibold">Conflito de sincronização</h1>
        <p class="text-sm text-slate-500">{{ $coleta->orcamento->cliente->nome }} · registro enviado por {{ $conflito->usuario->nome }} em {{ $conflito->recebida_em->format('d/m/Y H:i') }}</p>
    </div>

    <div class="alerta border-amber-200 bg-amber-50 text-amber-900">
        <strong>Motivo do conflito:</strong> {{ $conflito->motivo_conflito }} A coleta não foi alterada pelo envio.
    </div>
    @error('sincronizacao') <div class="alerta alerta-erro">{{ $message }}</div> @enderror
    @error('amostras') <div class="alerta alerta-erro">{{ $message }}</div> @enderror

    <div class="grid items-start gap-4 lg:grid-cols-2">
        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Coleta atual</h2>
            <dl class="grid grid-cols-[9rem_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-slate-500">Situação</dt><dd><x-selo-coleta :situacao="$coleta->situacao" /></dd>
                <dt class="text-slate-500">Agendada para</dt><dd>{{ $coleta->quando() }}</dd>
                <dt class="text-slate-500">Coletor</dt><dd>{{ $coleta->coletor->nome }}</dd>
                @if ($coleta->motivo)<dt class="text-slate-500">Motivo</dt><dd>{{ $coleta->motivo }}</dd>@endif
                @if ($coleta->data_hora_coleta)<dt class="text-slate-500">Registrada</dt><dd>{{ $coleta->data_hora_coleta->format('d/m/Y H:i') }} ({{ $coleta->nomeOrigemRegistro() }})</dd>@endif
            </dl>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($coleta->amostras as $a)
                    <li class="flex items-center justify-between gap-2 py-1.5">
                        <span><span class="font-mono">{{ $a->codigo }}</span> · {{ $a->pontoColeta->identificacao }}</span>
                        <x-selo-amostra :situacao="$a->situacao" />
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('coletas.show', $coleta) }}" class="text-sm text-teal-700 hover:underline">Abrir a coleta</a>
        </div>

        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Registro recebido do celular</h2>
            <dl class="grid grid-cols-[9rem_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-slate-500">Registro</dt><dd>{{ $dados['acao'] === Coleta::NAO_REALIZADA ? 'Coleta não realizada' : 'Coleta realizada' }}</dd>
                <dt class="text-slate-500">Data e hora</dt><dd>{{ \Illuminate\Support\Carbon::parse($dados['data_hora_coleta'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
                <dt class="text-slate-500">Origem</dt><dd>{{ Coleta::ORIGENS[$conflito->origem] ?? $conflito->origem }}</dd>
                @if (! empty($dados['condicoes_tempo']))<dt class="text-slate-500">Condições do tempo</dt><dd>{{ $dados['condicoes_tempo'] }}</dd>@endif
                <dt class="text-slate-500">Localização</dt>
                <dd>
                    @if (isset($dados['latitude']))
                        <a class="text-teal-700 hover:underline" target="_blank" rel="noopener"
                           href="https://www.openstreetmap.org/?mlat={{ $dados['latitude'] }}&mlon={{ $dados['longitude'] }}#map=17/{{ $dados['latitude'] }}/{{ $dados['longitude'] }}">{{ $dados['latitude'] }}, {{ $dados['longitude'] }}</a>
                    @else
                        Não informada
                    @endif
                </dd>
                @if ($dados['acao'] === Coleta::NAO_REALIZADA)
                    <dt class="text-slate-500">Motivo</dt><dd>{{ Coleta::MOTIVOS_NAO_COLETA[$dados['motivo_nao_realizada']] ?? '' }} {{ $dados['motivo_nao_realizada_detalhe'] ?? '' }}</dd>
                @endif
            </dl>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($dados['amostras'] ?? [] as $linha)
                    @php($a = $amostras->get($linha['id']))
                    <li class="space-y-1 py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span><span class="font-mono">{{ $a?->codigo ?? '#'.$linha['id'] }}</span> · {{ $a?->pontoColeta->identificacao }}</span>
                            <x-selo-amostra :situacao="$linha['situacao']" />
                        </div>
                        <div class="text-xs text-slate-600">
                            {{ collect([
                                isset($linha['temperatura']) ? Numero::decimal($linha['temperatura']).' °C' : null,
                                ...collect($linha['medicoes'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v, $id) => ($parametros[$id]->nome ?? 'Parâmetro').': '.Numero::decimal($v))->values(),
                                isset($linha['motivo']) ? Coleta::MOTIVOS_NAO_COLETA[$linha['motivo']] ?? null : null,
                                $linha['observacoes'] ?? null,
                            ])->filter()->implode(' · ') }}
                        </div>
                        @if ($fotos->has($linha['id']))
                            <div class="flex gap-2">
                                @foreach ($fotos[$linha['id']] as $foto)
                                    <a href="{{ route('fotos.show', $foto) }}" target="_blank"><img src="{{ route('fotos.show', $foto) }}" alt="Foto da amostra" class="h-16 w-16 rounded object-cover"></a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @if ($conflito->situacao === 'CONFLITO')
        <div class="cartao grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('conflitos.aceitar', $conflito) }}" class="space-y-2">
                @csrf
                <h2 class="font-semibold">Aceitar registro</h2>
                <p class="text-sm text-slate-600">A coleta passa a ser registrada como feita pelo coletor, com os dados do celular. Se estava cancelada, reagendada ou não realizada, volta para ele na data da coleta.</p>
                <button type="submit" class="botao botao-primario">Aceitar registro</button>
            </form>
            <form method="POST" action="{{ route('conflitos.descartar', $conflito) }}" class="space-y-2" novalidate>
                @csrf
                <h2 class="font-semibold">Descartar registro</h2>
                <label for="motivo_descarte" class="rotulo">Motivo *</label>
                <input id="motivo_descarte" name="motivo_descarte" maxlength="255" value="{{ old('motivo_descarte') }}"
                       @class(['campo', 'campo-erro' => $errors->has('motivo_descarte')])>
                @error('motivo_descarte') <p class="erro">{{ $message }}</p> @enderror
                <button type="submit" class="botao botao-perigo">Descartar registro</button>
            </form>
        </div>
    @else
        <div class="cartao text-sm">
            <strong>{{ $conflito->nomeSituacao() }}</strong> por {{ $conflito->resolvidaPor?->nome }} em {{ $conflito->resolvida_em?->format('d/m/Y H:i') }}.
            @if ($conflito->motivo_descarte) Motivo: {{ $conflito->motivo_descarte }}@endif
        </div>
    @endif
</x-layout>
