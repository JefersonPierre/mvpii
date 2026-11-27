{{-- UC16 – Registrar coleta (RF33, RN28, RN32) --}}
@use('App\Models\Amostra')
@use('App\Models\Coleta')
@php($previstas = $coleta->amostras->where('situacao', Amostra::PREVISTA))

<x-layout titulo="Registrar coleta">
    <div>
        <a href="{{ route('coletas.show', $coleta) }}" class="text-sm text-teal-700 hover:underline">← Coleta de {{ $coleta->quando() }}</a>
        <h1 class="text-2xl font-semibold">Registrar coleta</h1>
        <p class="text-sm text-slate-500">
            {{ $coleta->orcamento->cliente->nome }} · agendada para {{ $coleta->quando() }} · responsável: {{ $coleta->coletor->nome }}
        </p>
    </div>

    <form method="POST" action="{{ route('coletas.registro', $coleta) }}" class="space-y-4" novalidate>
        @csrf
        @error('coleta') <div class="alerta alerta-erro">{{ $message }}</div> @enderror

        <div class="cartao grid gap-4 md:grid-cols-[16rem_1fr]">
            <div>
                <label for="data_hora_coleta" class="rotulo">Data e hora da coleta *</label>
                <input id="data_hora_coleta" name="data_hora_coleta" type="datetime-local" required max="{{ now()->format('Y-m-d\TH:i') }}"
                       value="{{ old('data_hora_coleta', now()->format('Y-m-d\TH:i')) }}"
                       @class(['campo', 'campo-erro' => $errors->has('data_hora_coleta')])>
                @error('data_hora_coleta') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="condicoes_tempo" class="rotulo">Condições do tempo</label>
                <input id="condicoes_tempo" name="condicoes_tempo" maxlength="150" value="{{ old('condicoes_tempo') }}"
                       placeholder="Ex.: ensolarado, sem chuva nas últimas 24 h"
                       @class(['campo', 'campo-erro' => $errors->has('condicoes_tempo')])>
                @error('condicoes_tempo') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Amostras</h2>
            @error('amostras') <p class="erro">{{ $message }}</p> @enderror
            <div class="overflow-x-auto">
                <table class="tabela min-w-[900px]">
                    <thead>
                        <tr><th>Código</th><th>Ponto de coleta</th><th>Situação</th><th class="w-32">Temperatura (°C)</th><th>Motivo, se não coletada</th><th>Observações</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($previstas as $amostra)
                            @php($campo = fn ($c) => "amostras[{$amostra->id}][{$c}]")
                            @php($erro = fn ($c) => $errors->first("amostras.{$amostra->id}.{$c}"))
                            <tr>
                                <td class="font-mono whitespace-nowrap">{{ $amostra->codigo }}</td>
                                <td>
                                    {{ $amostra->pontoColeta->identificacao }}
                                    <div class="text-xs text-slate-500">{{ $amostra->descricaoItens() }}</div>
                                </td>
                                <td>
                                    <select name="{{ $campo('situacao') }}" aria-label="Situação da amostra {{ $amostra->codigo }}" @class(['campo', 'campo-erro' => $erro('situacao')])>
                                        @foreach ([Amostra::COLETADA => 'Coletada', Amostra::NAO_COLETADA => 'Não coletada'] as $valor => $rotulo)
                                            <option value="{{ $valor }}" @selected(old("amostras.{$amostra->id}.situacao", Amostra::COLETADA) === $valor)>{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                    @if ($erro('situacao')) <p class="erro">{{ $erro('situacao') }}</p> @endif
                                </td>
                                <td>
                                    <input name="{{ $campo('temperatura') }}" inputmode="decimal" aria-label="Temperatura da amostra {{ $amostra->codigo }}"
                                           value="{{ old("amostras.{$amostra->id}.temperatura") }}" @class(['campo font-mono', 'campo-erro' => $erro('temperatura')])>
                                    @if ($erro('temperatura')) <p class="erro">{{ $erro('temperatura') }}</p> @endif
                                </td>
                                <td>
                                    <select name="{{ $campo('motivo') }}" aria-label="Motivo da amostra {{ $amostra->codigo }} não coletada" @class(['campo', 'campo-erro' => $erro('motivo')])>
                                        <option value="">—</option>
                                        @foreach (Coleta::MOTIVOS_NAO_COLETA as $valor => $rotulo)
                                            <option value="{{ $valor }}" @selected(old("amostras.{$amostra->id}.motivo") === $valor)>{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                    @if ($erro('motivo')) <p class="erro">{{ $erro('motivo') }}</p> @endif
                                </td>
                                <td>
                                    <input name="{{ $campo('observacoes') }}" maxlength="255" aria-label="Observações da amostra {{ $amostra->codigo }}"
                                           value="{{ old("amostras.{$amostra->id}.observacoes") }}" @class(['campo', 'campo-erro' => $erro('observacoes')])>
                                    @if ($erro('observacoes')) <p class="erro">{{ $erro('observacoes') }}</p> @endif
                                </td>
                            </tr>
                            {{-- RF43 / RN46: medições de campo da amostra --}}
                            @php($deCampo = $amostra->parametrosDeCampo())
                            @if ($deCampo->isNotEmpty())
                                <tr class="bg-teal-50/40">
                                    <td></td>
                                    <td colspan="5">
                                        <div class="flex flex-wrap items-end gap-3">
                                            <span class="text-xs font-medium text-teal-800">Medições de campo:</span>
                                            @foreach ($deCampo as $p)
                                                <label class="text-xs">
                                                    <span class="block text-slate-600">{{ $p->nome }} ({{ $p->unidade }})</span>
                                                    <input name="amostras[{{ $amostra->id }}][medicoes][{{ $p->id }}]" inputmode="decimal"
                                                           value="{{ old("amostras.{$amostra->id}.medicoes.{$p->id}") }}" @class(['campo w-28 font-mono', 'campo-erro' => $errors->has("amostras.{$amostra->id}.medicoes.{$p->id}")])>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if ($errors->has("amostras.{$amostra->id}.medicoes.*"))<p class="erro">Informe as medições em números, ex.: 7,2.</p>@endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="alerta alerta-info">A amostra não coletada volta ao saldo do orçamento para um novo agendamento.</p>
            <div class="flex justify-end">
                <button type="submit" name="acao" value="realizada" class="botao botao-primario">Registrar coleta</button>
            </div>
        </div>

        {{-- UC16 3b: nenhuma amostra coletada --}}
        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Coleta não realizada</h2>
            <p class="text-sm text-slate-500">Nenhuma amostra coletada? Registre a coleta como Não realizada e informe o motivo; a Recepção reagenda.</p>
            <div class="grid gap-4 md:grid-cols-[16rem_1fr_auto] md:items-end">
                <div>
                    <label for="motivo_nao_realizada" class="rotulo">Motivo *</label>
                    <select id="motivo_nao_realizada" name="motivo_nao_realizada" @class(['campo', 'campo-erro' => $errors->has('motivo_nao_realizada')])>
                        <option value="">Selecione…</option>
                        @foreach (Coleta::MOTIVOS_NAO_COLETA as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('motivo_nao_realizada') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    @error('motivo_nao_realizada') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="motivo_nao_realizada_detalhe" class="rotulo">Detalhe</label>
                    <input id="motivo_nao_realizada_detalhe" name="motivo_nao_realizada_detalhe" maxlength="255" value="{{ old('motivo_nao_realizada_detalhe') }}"
                           @class(['campo', 'campo-erro' => $errors->has('motivo_nao_realizada_detalhe')])>
                    @error('motivo_nao_realizada_detalhe') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <button type="submit" name="acao" value="nao_realizada" class="botao botao-secundario">Registrar como não realizada</button>
            </div>
        </div>
    </form>
</x-layout>
