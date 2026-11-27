{{-- UC26 / UC27 / UC28 – Laudo: PDF, envio, retificação e revisões (RF52–RF54, RN55) --}}
@use('App\Services\EmissaoLaudos')
@php($amostra = $laudo->amostra)

<x-layout :titulo="'Laudo '.$laudo->numeroComRevisao()">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <a href="{{ route('laudos.index') }}" class="text-sm text-teal-700 hover:underline">← Laudos</a>
            <h1 class="text-2xl font-semibold">Laudo <span class="font-mono">{{ $laudo->numeroComRevisao() }}</span></h1>
            <p class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <span @class(['selo', 'bg-teal-100 text-teal-800' => $laudo->vigente(), 'bg-slate-200 text-slate-600' => ! $laudo->vigente()])>{{ $laudo->nomeSituacao() }}</span>
                <x-selo-conformidade :valor="$laudo->conclusao" />
                Amostra <a href="{{ route('amostras.show', $amostra) }}" class="font-mono text-teal-700 hover:underline">{{ $amostra->codigo }}</a>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('laudos.pdf', $laudo) }}" target="_blank" class="botao botao-primario">Abrir PDF</a>
            @if ($laudo->vigente())
                @can('laudos.enviar')
                    <form method="POST" action="{{ route('laudos.enviar', $laudo) }}">
                        @csrf
                        <button type="submit" class="botao botao-secundario">{{ $laudo->enviado_em ? 'Reenviar ao cliente' : 'Enviar ao cliente' }}</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if (! $laudo->vigente() && $laudo->substituidoPor)
        <div class="alerta alerta-info">Substituído pelo laudo <a href="{{ route('laudos.show', $laudo->substituidoPor) }}" class="font-mono underline">{{ $laudo->substituidoPor->numeroComRevisao() }}</a>.</div>
    @endif
    @if ($laudo->vigente() && $amostra->motivo_retificacao)
        <div class="alerta border-amber-200 bg-amber-50 text-amber-900">Retificação em andamento: {{ $amostra->motivo_retificacao }}. Este laudo continua vigente até a emissão do novo.</div>
    @endif

    <div class="grid items-start gap-4 lg:grid-cols-2">
        <div class="cartao">
            <dl class="grid grid-cols-[10rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                <dt class="text-slate-500">Cliente</dt><dd>{{ $amostra->coleta->orcamento->cliente->nome }}</dd>
                <dt class="text-slate-500">Ponto de coleta</dt><dd>{{ $amostra->pontoColeta->identificacao }}</dd>
                <dt class="text-slate-500">Emitido em</dt><dd>{{ $laudo->emitido_em->format('d/m/Y H:i') }} por {{ $laudo->emitidoPor->nome }}</dd>
                @if ($laudo->substitui)<dt class="text-slate-500">Substitui</dt><dd><a href="{{ route('laudos.show', $laudo->substitui) }}" class="font-mono text-teal-700 hover:underline">{{ $laudo->substitui->numeroComRevisao() }}</a> – {{ $laudo->motivo_retificacao }}</dd>@endif
                <dt class="text-slate-500">Envio</dt>
                <dd>{{ $laudo->enviado_em ? $laudo->envio_email.' em '.$laudo->enviado_em->format('d/m/Y H:i') : 'Não enviado' }}</dd>
                <dt class="text-slate-500">Código de verificação</dt>
                <dd><a href="{{ $laudo->urlVerificacao() }}" target="_blank" class="font-mono text-teal-700 hover:underline">{{ EmissaoLaudos::formatarCodigo($laudo->codigo_verificacao) }}</a></dd>
                <dt class="text-slate-500">Resumo do PDF (SHA-256)</dt><dd class="font-mono text-xs break-all">{{ $laudo->pdf_hash }}</dd>
            </dl>
        </div>

        @can('laudos.emitir')
            @if ($laudo->vigente() && ! $amostra->motivo_retificacao)
                <form method="POST" action="{{ route('laudos.retificar', $laudo) }}" class="cartao space-y-3" novalidate>
                    @csrf
                    <h2 class="text-lg font-semibold">Retificar laudo</h2>
                    <p class="text-sm text-slate-600">O novo laudo mantém o número, com a revisão {{ $laudo->revisao + 1 }}, e este passa a Substituído (RN55).</p>
                    <div>
                        <label for="motivo_retificacao" class="rotulo">Motivo da retificação *</label>
                        <input id="motivo_retificacao" name="motivo_retificacao" maxlength="255" value="{{ old('motivo_retificacao') }}"
                               @class(['campo', 'campo-erro' => $errors->has('motivo_retificacao')])>
                        @error('motivo_retificacao') <p class="erro">{{ $message }}</p> @enderror
                    </div>
                    <input type="hidden" name="reabrir_resultados" value="0">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="reabrir_resultados" value="1" @checked(old('reabrir_resultados', '1') === '1')>
                        Reabrir os resultados para correção (desmarque se a correção é só nos dados do cliente ou do ponto)
                    </label>
                    <button type="submit" class="botao botao-secundario">Iniciar retificação</button>
                </form>
            @endif
        @endcan
    </div>

    <div class="cartao space-y-3">
        <h2 class="text-lg font-semibold">Revisões e histórico</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach ($amostra->laudos as $l)
                <li class="flex items-center justify-between gap-2 py-1.5">
                    <a href="{{ route('laudos.show', $l) }}" @class(['font-mono hover:underline', 'font-semibold text-teal-800' => $l->is($laudo)])>{{ $l->numeroComRevisao() }}</a>
                    <span class="text-slate-500">{{ $l->emitido_em->format('d/m/Y') }} · {{ $l->nomeSituacao() }}</span>
                </li>
            @endforeach
        </ul>
        <x-tabela-historico :registros="$historico" :campos="['numero' => 'Laudo', 'situacao' => 'Situação', 'conclusao' => 'Conclusão', 'pdf_hash' => 'Hash do PDF', 'envio_email' => 'Enviado para', 'motivo_retificacao' => 'Motivo da retificação']" />
    </div>
</x-layout>
