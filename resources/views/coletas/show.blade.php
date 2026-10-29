{{-- UC15 – detalhes da coleta (RF31, RF32); UC14 1a/1b – reagendar e cancelar (RF29) --}}
@use('App\Models\Coleta')
@use('App\Services\Rastreabilidade')
@use('App\Support\Numero')
@php
    $cliente = $coleta->orcamento->cliente;
    $contato = $cliente->contatoPrincipal;
    $pontos = $coleta->amostras->pluck('pontoColeta')->unique('id');
@endphp

<x-layout :titulo="'Coleta de '.$coleta->data->format('d/m/Y')">
    <a href="{{ route('coletas.index', ['semana' => $coleta->data->toDateString()]) }}" class="text-sm text-teal-700 hover:underline">← Agenda de coletas</a>

    <div class="cartao space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold">{{ $cliente->nome }}</h1>
                    <x-selo-coleta :situacao="$coleta->situacao" />
                </div>
                <p class="text-sm text-slate-500">
                    {{ $coleta->quando() }} · coletor: {{ $coleta->coletor->nome }}
                    @can('orcamentos')
                        · orçamento <a href="{{ route('orcamentos.show', $coleta->orcamento) }}" class="font-mono text-teal-700 hover:underline">{{ $coleta->orcamento->numeroComRevisao() }}</a>
                    @endcan
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('coletas.roteiro')
                    <a href="{{ route('coletas.roteiro', ['coletor' => $coleta->coletor_id, 'data' => $coleta->data->toDateString()]) }}" class="botao botao-secundario">Roteiro do dia</a>
                @endcan
                <a href="{{ route('coletas.ficha', $coleta) }}" target="_blank" class="botao botao-secundario">Imprimir ficha de coleta</a>
                @if ($coleta->podeRegistrar() && Gate::allows('registrar', $coleta))
                    <a href="{{ route('coletas.registro', $coleta) }}" class="botao botao-primario">Registrar coleta</a>
                @endif
                @can('coletas.agendar')
                    @if ($coleta->podeReagendar())
                        <a href="{{ route('coletas.edit', $coleta) }}" class="botao botao-secundario">Reagendar</a>
                    @endif
                    @if ($coleta->podeCancelar())
                        <button type="button" class="botao botao-perigo" data-abrir-dialogo="dialogo-cancelar">Cancelar coleta</button>
                    @endif
                @endcan
            </div>
        </div>

        @if ($coleta->motivo && in_array($coleta->situacao, [Coleta::AGENDADA, Coleta::CANCELADA], true))
            <div class="alerta border-slate-200 bg-slate-50 text-slate-700">
                {{ $coleta->situacao === Coleta::CANCELADA ? 'Motivo do cancelamento' : 'Motivo do último reagendamento' }}: {{ $coleta->motivo }}
            </div>
        @endif

        <dl class="grid gap-x-8 gap-y-4 text-sm md:grid-cols-2">
            <div>
                <dt class="text-slate-500">Contato</dt>
                <dd>
                    @if ($contato)
                        {{ $contato->nome }}@if ($contato->telefone) · <span class="font-mono">{{ $contato->telefoneFormatado() }}</span>@endif
                        @if ($contato->email) · {{ $contato->email }} @endif
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div><dt class="text-slate-500">Instruções para o coletor</dt><dd class="whitespace-pre-line">{{ $coleta->instrucoes ?: '—' }}</dd></div>
            <div class="md:col-span-2">
                <dt class="text-slate-500">Pontos de coleta</dt>
                <dd>
                    <ul class="space-y-1">
                        @foreach ($pontos as $ponto)
                            <li>
                                <span class="font-medium">{{ $ponto->identificacao }}</span> – {{ $ponto->enderecoCompleto() ?: $cliente->enderecoCompleto() }}
                                @if ($ponto->referencia)<span class="text-slate-500">({{ $ponto->referencia }})</span>@endif
                                @if ($ponto->linkMapa())
                                    <a href="{{ $ponto->linkMapa() }}" target="_blank" rel="noopener" class="text-xs text-teal-700 hover:underline">Ver no mapa</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </dd>
            </div>
            <div><dt class="text-slate-500">Agendada por</dt><dd>{{ $coleta->criadoPor?->nome ?? '—' }} em {{ $coleta->criado_em->format('d/m/Y H:i') }}</dd></div>
            @if ($coleta->registradaPor)
                <div>
                    <dt class="text-slate-500">Coleta registrada por</dt>
                    <dd>
                        {{ $coleta->registradaPor->nome }}
                        @if ($coleta->data_hora_coleta) · coletada em {{ $coleta->data_hora_coleta->format('d/m/Y H:i') }} @endif
                        @if ($coleta->condicoes_tempo) · {{ $coleta->condicoes_tempo }} @endif
                    </dd>
                </div>
                {{-- RN41 / RN44: origem do registro e localização da coleta --}}
                <div>
                    <dt class="text-slate-500">Origem do registro</dt>
                    <dd>
                        {{ $coleta->nomeOrigemRegistro() ?? 'Computador' }}
                        @if ($coleta->registro_recebido_em && $coleta->origem_registro !== 'COMPUTADOR') · recebido no servidor em {{ $coleta->registro_recebido_em->format('d/m/Y H:i') }} @endif
                    </dd>
                </div>
                @if ($coleta->latitude_coleta !== null)
                    <div>
                        <dt class="text-slate-500">Localização da coleta</dt>
                        <dd>
                            <a href="https://www.openstreetmap.org/?mlat={{ $coleta->latitude_coleta }}&mlon={{ $coleta->longitude_coleta }}#map=17/{{ $coleta->latitude_coleta }}/{{ $coleta->longitude_coleta }}" target="_blank" rel="noopener" class="font-mono text-teal-700 hover:underline">{{ $coleta->latitude_coleta }}, {{ $coleta->longitude_coleta }}</a>
                            @if ($coleta->distancia_ponto_metros !== null) · {{ $coleta->distancia_ponto_metros }} m do ponto @endif
                            @if ($coleta->conferir_localizacao)<span class="selo bg-amber-100 text-amber-800">Conferir: a mais de {{ Coleta::DISTANCIA_MAXIMA_METROS }} m do ponto</span>@endif
                        </dd>
                    </div>
                @endif
            @endif
            @if ($conflitos->isNotEmpty())
                <div class="md:col-span-2">
                    <dt class="text-slate-500">Registros do celular com conflito</dt>
                    <dd class="space-y-1">
                        @foreach ($conflitos as $c)
                            <div>{{ $c->recebida_em->format('d/m/Y H:i') }} · {{ $c->usuario->nome }} · {{ $c->nomeSituacao() }}
                                @can('coletas.conflitos')<a href="{{ route('conflitos.show', $c) }}" class="text-teal-700 hover:underline">Abrir</a>@endcan</div>
                        @endforeach
                    </dd>
                </div>
            @endif
            @if ($coleta->situacao === Coleta::NAO_REALIZADA)
                <div>
                    <dt class="text-slate-500">Motivo da coleta não realizada</dt>
                    <dd>{{ collect([$coleta->nomeMotivoNaoRealizada(), $coleta->motivo_nao_realizada_detalhe])->filter()->implode(' – ') }}</dd>
                </div>
            @endif
        </dl>

        <div class="space-y-2">
            <h2 class="text-lg font-semibold">Amostras ({{ $coleta->amostras->count() }})</h2>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[720px]">
                    <thead><tr><th>Código</th><th>Ponto de coleta</th><th>Itens a analisar</th><th>Situação</th><th class="text-right">Temperatura</th><th>Observações</th></tr></thead>
                    <tbody>
                        @foreach ($coleta->amostras as $amostra)
                            <tr>
                                <td>
                                    <a href="{{ route('amostras.show', $amostra) }}" class="font-mono text-teal-800 hover:underline">{{ $amostra->codigo }}</a>
                                </td>
                                <td>{{ $amostra->pontoColeta->identificacao }}</td>
                                <td class="text-sm">{{ $amostra->descricaoItens() }}</td>
                                <td><x-selo-amostra :situacao="$amostra->situacao" /></td>
                                <td class="text-right font-mono text-sm">{{ $amostra->temperatura_coleta !== null ? Numero::decimal($amostra->temperatura_coleta).' °C' : '—' }}</td>
                                <td class="text-sm">{{ collect([$amostra->nomeMotivoNaoColeta(), $amostra->observacoes_coleta])->filter()->implode(' – ') ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($registros)
        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Histórico da coleta</h2>
            <x-tabela-historico :registros="$registros" :campos="Rastreabilidade::NOMES_CAMPOS" />
        </div>
    @endif

    @can('coletas.agendar')
        {{-- UC14 1b / RN31: cancelar exige motivo --}}
        <dialog id="dialogo-cancelar" class="dialogo" aria-labelledby="titulo-cancelar" @if ($errors->has('motivo')) data-abrir-ao-carregar @endif>
            <form method="POST" action="{{ route('coletas.cancelar', $coleta) }}" class="space-y-4" novalidate>
                @csrf
                <h2 id="titulo-cancelar" class="text-lg font-semibold">Cancelar coleta</h2>
                <p>As amostras previstas serão canceladas e voltam ao saldo do orçamento. O cliente é avisado por e-mail.</p>
                <div>
                    <label for="motivo" class="rotulo">Motivo *</label>
                    <input id="motivo" name="motivo" maxlength="255" required value="{{ old('motivo') }}"
                           @class(['campo', 'campo-erro' => $errors->has('motivo')])>
                    @error('motivo') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="botao botao-secundario" data-fechar-dialogo>Voltar</button>
                    <button type="submit" class="botao botao-perigo-cheio">Cancelar coleta</button>
                </div>
            </form>
        </dialog>
    @endcan
</x-layout>
