{{-- UC17 – Registrar recebimento da amostra (RF34, RN33, RN34, RN35) --}}
@use('App\Models\Amostra')
@use('App\Services\RecebimentoAmostras')
@use('App\Support\CriteriosRecebimento')
@use('App\Support\Numero')
@php
    $minutos = fn (Amostra $a) => CriteriosRecebimento::minutosDesdeColeta($a->coleta->data_hora_coleta, now());
    $proprias = fn (Amostra $a) => RecebimentoAmostras::mesmoResponsavelDaColeta($a, auth()->id());
    $primeira = $selecionadas->first();
@endphp

<x-layout titulo="Receber amostras">
    <div>
        <h1 class="text-2xl font-semibold">Receber amostras</h1>
        <p class="text-sm text-slate-500">Amostras coletadas e ainda não recebidas no laboratório.</p>
    </div>

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(22rem,1fr)_1.4fr]">
        <div class="cartao space-y-4">
            <form method="GET" action="{{ route('amostras.receber') }}" class="flex items-end gap-2">
                <div class="flex-1">
                    <label for="codigo" class="rotulo">Código da amostra</label>
                    <input id="codigo" name="codigo" value="{{ $codigo }}" placeholder="AM-{{ now()->year }}-00001" autofocus class="campo font-mono">
                </div>
                <button type="submit" class="botao botao-secundario">Buscar</button>
            </form>
            @if ($aviso) <p class="alerta alerta-erro">{{ $aviso }}</p> @endif

            @if ($aReceber->isEmpty())
                <p class="text-slate-500">Nenhuma amostra aguardando recebimento.</p>
            @else
                <form method="GET" action="{{ route('amostras.receber') }}" class="space-y-3">
                    <div class="overflow-x-auto">
                        <table class="tabela min-w-[420px]">
                            <thead><tr><th><span class="sr-only">Selecionar</span></th><th>Código</th><th>Cliente e ponto</th><th>Coletada há</th></tr></thead>
                            <tbody>
                                @foreach ($aReceber as $a)
                                    @php($atrasada = CriteriosRecebimento::foraDoPrazo($minutos($a), $config->prazo_recebimento_horas))
                                    <tr @class(['bg-teal-50' => $selecionadas->contains('id', $a->id)])>
                                        <td>
                                            <input type="checkbox" name="amostras[]" value="{{ $a->id }}" aria-label="Selecionar {{ $a->codigo }}"
                                                   @checked($selecionadas->contains('id', $a->id)) @disabled($proprias($a))>
                                        </td>
                                        <td><a href="{{ route('amostras.receber', ['codigo' => $a->codigo]) }}" class="font-mono whitespace-nowrap text-teal-800 hover:underline">{{ $a->codigo }}</a></td>
                                        <td>
                                            {{ $a->coleta->orcamento->cliente->nome }}
                                            <div class="text-xs text-slate-500">{{ $a->pontoColeta->identificacao }}</div>
                                            @if ($proprias($a))
                                                <div class="text-xs font-medium text-amber-700">Você registrou a coleta desta amostra: o recebimento deve ser feito por outro analista.</div>
                                            @endif
                                        </td>
                                        <td @class(['font-mono text-sm whitespace-nowrap', 'font-semibold text-amber-700' => $atrasada])>{{ CriteriosRecebimento::duracao($minutos($a)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="ajuda">Para receber várias amostras da mesma coleta de uma vez, marque-as e confira em conjunto.</p>
                    <button type="submit" class="botao botao-secundario">Conferir selecionadas</button>
                </form>
            @endif
        </div>

        <div class="cartao space-y-4">
            @if ($selecionadas->isEmpty())
                <p class="text-slate-500">Informe o código da amostra ou selecione na lista as amostras a receber.</p>
            @else
                <div>
                    <h2 class="text-lg font-semibold">
                        @if ($selecionadas->count() === 1)
                            <span class="font-mono">{{ $primeira->codigo }}</span> · {{ $primeira->pontoColeta->identificacao }}
                        @else
                            {{ $selecionadas->count() }} amostras da coleta de {{ $primeira->coleta->data->format('d/m/Y') }}
                        @endif
                    </h2>
                    @foreach ($selecionadas as $a)
                        <p class="text-sm text-slate-500">
                            @if ($selecionadas->count() > 1)<span class="font-mono">{{ $a->codigo }}</span> · {{ $a->pontoColeta->identificacao }} · @endif
                            coletada em {{ $a->coleta->data_hora_coleta->format('d/m/Y H:i') }} por {{ $a->coletadaPor?->nome ?? $a->coleta->coletor->nome }}
                            @if ($a->temperatura_coleta !== null) a {{ Numero::decimal($a->temperatura_coleta) }} °C @endif
                            · {{ $a->descricaoItens() }}
                        </p>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('amostras.receber') }}" class="space-y-4" novalidate data-form-recebimento
                      data-prazo-horas="{{ $config->prazo_recebimento_horas }}" data-temperatura-maxima="{{ $config->temperatura_maxima_recebimento }}"
                      data-coletada-em="{{ $selecionadas->min(fn ($a) => $a->coleta->data_hora_coleta)->toIso8601String() }}">
                    @csrf
                    @foreach ($selecionadas as $a)
                        <input type="hidden" name="amostras[]" value="{{ $a->id }}">
                    @endforeach
                    @error('amostras') <p class="alerta alerta-erro">{{ $message }}</p> @enderror

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="recebida_em" class="rotulo">Data e hora do recebimento *</label>
                            <input id="recebida_em" name="recebida_em" type="datetime-local" required max="{{ now()->format('Y-m-d\TH:i') }}"
                                   value="{{ old('recebida_em', now()->format('Y-m-d\TH:i')) }}"
                                   @class(['campo', 'campo-erro' => $errors->has('recebida_em')])>
                            @error('recebida_em') <p class="erro">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="temperatura_recebimento" class="rotulo">Temperatura no recebimento (°C) *</label>
                            <input id="temperatura_recebimento" name="temperatura_recebimento" inputmode="decimal" required
                                   value="{{ old('temperatura_recebimento') }}"
                                   @class(['campo font-mono', 'campo-erro' => $errors->has('temperatura_recebimento')])>
                            @error('temperatura_recebimento') <p class="erro">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    {{-- RN35: alerta de prazo e temperatura, calculado também no servidor ao gravar --}}
                    <div class="alerta border-amber-200 bg-amber-50 text-amber-900" data-alertas-recebimento hidden></div>

                    <fieldset>
                        <legend class="rotulo">Condições do frasco</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            @foreach (Amostra::CONDICOES_FRASCO as $campo => $nome)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="{{ $campo }}" value="1" @checked(old($campo, ! $errors->any()))> {{ $nome }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label for="observacoes_recebimento" class="rotulo">Observação</label>
                        <input id="observacoes_recebimento" name="observacoes_recebimento" maxlength="255" value="{{ old('observacoes_recebimento') }}"
                               @class(['campo', 'campo-erro' => $errors->has('observacoes_recebimento')])>
                        <p class="ajuda">Obrigatória para aceitar amostra fora dos critérios de prazo ou temperatura.</p>
                        @error('observacoes_recebimento') <p class="erro">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label for="motivo_rejeicao" class="rotulo">Motivo da rejeição</label>
                            <select id="motivo_rejeicao" name="motivo_rejeicao" @class(['campo', 'campo-erro' => $errors->has('motivo_rejeicao')])>
                                <option value="">—</option>
                                @foreach (Amostra::MOTIVOS_REJEICAO as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('motivo_rejeicao') === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('motivo_rejeicao') <p class="erro">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" name="decisao" value="REJEITAR" class="botao botao-perigo">Rejeitar</button>
                        <button type="submit" name="decisao" value="ACEITAR" class="botao botao-primario">Aceitar</button>
                    </div>
                    <p class="ajuda">A amostra rejeitada volta ao saldo do orçamento para nova coleta.</p>
                </form>

                <p class="border-t border-slate-200 pt-3 text-sm text-slate-500">
                    Critérios de recebimento: até {{ $config->prazo_recebimento_horas }} h entre a coleta e o recebimento e no máximo
                    {{ Numero::decimal($config->temperatura_maxima_recebimento) }} °C.
                    @can('configuracoes') <a href="{{ route('configuracoes') }}" class="underline">Alterar em Configurações</a>. @endcan
                </p>
            @endif
        </div>
    </div>
</x-layout>
