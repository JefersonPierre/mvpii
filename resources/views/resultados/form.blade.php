{{-- UC24 – Lançar resultados da amostra (RF48–RF50, RN50–RN52), protótipo P17 --}}
@use('App\Models\Resultado')
@use('App\Support\Numero')

<x-layout titulo="Lançar resultados">
    <div>
        <a href="{{ route('resultados.index') }}" class="text-sm text-teal-700 hover:underline">← Resultados</a>
        <h1 class="text-2xl font-semibold">Resultados da amostra <span class="font-mono">{{ $amostra->codigo }}</span></h1>
        <p class="text-sm text-slate-500">{{ $amostra->coleta->orcamento->tipoAmostra->nome }} · <x-selo-amostra :situacao="$amostra->situacao" /></p>
    </div>
    @include('resultados._cabecalho')

    @if ($amostra->motivo_devolucao)
        <div class="alerta border-amber-200 bg-amber-50 text-amber-900"><strong>Devolvida na revisão:</strong> {{ $amostra->motivo_devolucao }}</div>
    @endif
    @if ($amostra->motivo_retificacao)
        <div class="alerta border-amber-200 bg-amber-50 text-amber-900"><strong>Retificação do laudo:</strong> {{ $amostra->motivo_retificacao }}</div>
    @endif

    <form method="POST" action="{{ route('resultados.update', $amostra) }}" class="space-y-4" data-form-resultados novalidate>
        @csrf
        @method('PUT')
        @error('amostra') <div class="alerta alerta-erro">{{ $message }}</div> @enderror
        @error('resultados') <div class="alerta alerta-erro">{{ $message }}</div> @enderror

        <div class="cartao space-y-2">
            <h2 class="text-lg font-semibold">Legislação de referência</h2>
            @if ($escolher)
                <p class="text-sm text-slate-600">Há mais de uma legislação vigente na data da coleta para este tipo de amostra. Escolha a de referência (RN51); a escolha vale para esta amostra.</p>
                <select name="legislacao_id" aria-label="Legislação de referência" @class(['campo max-w-md', 'campo-erro' => $errors->has('legislacao_id')]) data-recarregar-legislacao>
                    <option value="">Selecione…</option>
                    @foreach ($legislacoes as $l)
                        <option value="{{ $l->id }}" @selected((int) old('legislacao_id', $legislacao?->id) === $l->id)>{{ $l->nome }}</option>
                    @endforeach
                </select>
                @error('legislacao_id') <p class="erro">{{ $message }}</p> @enderror
            @elseif ($legislacao)
                <p>{{ $legislacao->nome }} <span class="text-sm text-slate-500">– vigente na data da coleta</span></p>
            @else
                <p class="alerta alerta-info">Não há legislação vigente para {{ mb_strtolower($amostra->coleta->orcamento->tipoAmostra->nome) }} na data da coleta: os resultados ficarão sem comparação e o laudo informará que não há legislação de referência.</p>
            @endif
        </div>

        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Parâmetros</h2>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[1100px]">
                    <thead>
                        <tr><th>Parâmetro</th><th>LQ</th><th>Limite</th><th class="w-56">Resultado</th><th class="w-40">Data da análise</th><th>Conformidade</th><th>Observações</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as ['parametro' => $p, 'limite' => $limite, 'resultado' => $r])
                            @php($campo = fn ($c) => "resultados[{$p->id}][{$c}]")
                            @php($velho = fn ($c, $padrao = null) => old("resultados.{$p->id}.{$c}", $padrao))
                            @php($erro = fn ($c) => $errors->first("resultados.{$p->id}.{$c}"))
                            <tr data-linha-resultado data-tipo="{{ $legislacao ? ($limite?->tipo ?? '') : 'SEM' }}" data-min="{{ $limite?->valor_minimo }}" data-max="{{ $limite?->valor_maximo }}" data-lq="{{ $p->limite_quantificacao }}">
                                <td>
                                    <span class="font-medium">{{ $p->nome }}</span>
                                    <span class="block text-xs text-slate-500">{{ $p->unidade }}{{ $p->metodo ? ' · '.$p->metodo : '' }}</span>
                                    @if ($r?->origem === Resultado::CAMPO)<span class="selo bg-teal-100 text-teal-800">Medido em campo</span>@endif
                                </td>
                                <td class="font-mono text-xs">{{ Numero::decimal($p->limite_quantificacao) ?: '—' }}</td>
                                <td class="text-sm whitespace-nowrap">{{ $legislacao ? ($limite?->descricao() ?? 'Sem limite') : '—' }}</td>
                                <td>
                                    @if ($limite?->tipo === 'AUSENCIA')
                                        <select name="{{ $campo('presenca') }}" aria-label="Resultado de {{ $p->nome }}" data-presenca @class(['campo', 'campo-erro' => $erro('presenca')])>
                                            <option value="">—</option>
                                            @foreach (Resultado::PRESENCAS as $valor => $rotulo)
                                                <option value="{{ $valor }}" @selected($velho('presenca', $r?->presenca) === $valor)>{{ $rotulo }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <input name="{{ $campo('valor') }}" inputmode="decimal" aria-label="Resultado de {{ $p->nome }}" data-valor
                                                   value="{{ $velho('valor', Numero::campo($r?->valor)) }}" @class(['campo font-mono', 'campo-erro' => $erro('valor')])>
                                            <label class="flex items-center gap-1 text-xs whitespace-nowrap">
                                                <input type="checkbox" name="{{ $campo('menor_que_lq') }}" value="1" data-lq-marcado @checked($velho('menor_que_lq', $r?->menor_que_lq))> &lt; LQ
                                            </label>
                                        </div>
                                    @endif
                                    @if ($erro('valor')) <p class="erro">{{ $erro('valor') }}</p> @endif
                                    @if ($r?->lancadoPor)<span class="ajuda block">por {{ $r->lancadoPor->nome }}</span>@endif
                                </td>
                                <td>
                                    <input type="date" name="{{ $campo('data_analise') }}" max="{{ today()->toDateString() }}" aria-label="Data da análise de {{ $p->nome }}"
                                           value="{{ $velho('data_analise', $r?->data_analise?->toDateString() ?? today()->toDateString()) }}" @class(['campo', 'campo-erro' => $erro('data_analise')])>
                                    @if ($erro('data_analise')) <p class="erro">{{ $erro('data_analise') }}</p> @endif
                                </td>
                                <td data-conformidade><x-selo-conformidade :valor="$r?->conformidade" /></td>
                                <td>
                                    <input name="{{ $campo('observacoes') }}" maxlength="255" aria-label="Observações de {{ $p->nome }}"
                                           value="{{ $velho('observacoes', $r?->observacoes) }}" class="campo">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ajuda">A conformidade é calculada pelo sistema com o limite da legislação e não pode ser alterada (RN52).</p>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="submit" name="acao" value="salvar" class="botao botao-secundario">Salvar</button>
                <button type="submit" name="acao" value="enviar" class="botao botao-primario">Enviar para revisão</button>
            </div>
        </div>
    </form>
</x-layout>
