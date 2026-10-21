{{-- Uma amostra do agendamento: ponto de coleta e itens a analisar (RF27). $n é o índice da linha. --}}
@php($marcados = array_map('intval', (array) ($linha['itens'] ?? [])))
<div data-amostra class="grid gap-3 py-3 md:grid-cols-[minmax(12rem,1fr)_2fr_auto]">
    <div>
        <label class="rotulo" for="amostra-{{ $n }}-ponto">Ponto de coleta</label>
        <select id="amostra-{{ $n }}-ponto" name="amostras[{{ $n }}][ponto_coleta_id]"
                @class(['campo', 'campo-erro' => $errors->has("amostras.$n.ponto_coleta_id")])>
            <option value="">Selecione…</option>
            @foreach ($pontos as $p)
                <option value="{{ $p->id }}" @selected((int) ($linha['ponto_coleta_id'] ?? 0) === $p->id)>{{ $p->identificacao }}</option>
            @endforeach
        </select>
        @error("amostras.$n.ponto_coleta_id") <p class="erro">{{ $message }}</p> @enderror
    </div>
    <fieldset>
        <legend class="rotulo">Itens a analisar</legend>
        <div class="flex flex-wrap gap-x-4 gap-y-1">
            @foreach ($saldo as $id => $s)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="amostras[{{ $n }}][itens][]" value="{{ $id }}" data-item-amostra
                           @checked(in_array($id, $marcados, true))>
                    {{ preg_replace('/^Pacote /', '', $s['item']->descricao) }}
                </label>
            @endforeach
        </div>
        @error("amostras.$n.itens") <p class="erro">{{ $message }}</p> @enderror
    </fieldset>
    <div class="self-end">
        <button type="button" class="botao botao-pequeno botao-perigo" data-remover-amostra>Remover</button>
    </div>
</div>
