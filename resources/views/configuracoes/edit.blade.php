{{-- UC12 – Configurar parâmetros comerciais (RF22) --}}
@use('App\Support\Numero')

<x-layout titulo="Configurações">
    <div>
        <h1 class="text-2xl font-semibold">Configurações do orçamento</h1>
        <p class="text-sm text-slate-500">Valores padrão usados ao elaborar um orçamento. Mudanças valem para os próximos; os já emitidos não mudam.</p>
    </div>

    <form method="POST" action="{{ route('configuracoes') }}" class="cartao max-w-2xl space-y-4" novalidate>
        @csrf
        @method('PUT')
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="validade_dias" class="rotulo">Validade padrão (dias) *</label>
                <input id="validade_dias" name="validade_dias" type="number" min="1" max="365"
                       value="{{ old('validade_dias', $configuracao->validade_dias) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('validade_dias')])>
                @error('validade_dias') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="taxa_coleta" class="rotulo">Taxa de coleta padrão (R$) *</label>
                <input id="taxa_coleta" name="taxa_coleta" inputmode="decimal"
                       value="{{ old('taxa_coleta', Numero::campo($configuracao->taxa_coleta, true)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('taxa_coleta')])>
                @error('taxa_coleta') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="desconto_maximo" class="rotulo">Desconto máximo (%) *</label>
                <input id="desconto_maximo" name="desconto_maximo" inputmode="decimal"
                       value="{{ old('desconto_maximo', Numero::decimal($configuracao->desconto_maximo)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('desconto_maximo')])>
                @error('desconto_maximo') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="condicoes_comerciais" class="rotulo">Condições comerciais (vão no PDF do orçamento)</label>
            <textarea id="condicoes_comerciais" name="condicoes_comerciais" rows="5" maxlength="5000"
                      @class(['campo', 'campo-erro' => $errors->has('condicoes_comerciais')])>{{ old('condicoes_comerciais', $configuracao->condicoes_comerciais) }}</textarea>
            @error('condicoes_comerciais') <p class="erro">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="botao botao-primario">Salvar configurações</button>
        </div>
    </form>

    <div class="cartao space-y-3">
        <h2 class="text-lg font-semibold">Histórico de alterações</h2>
        <x-tabela-historico :registros="$registros"
            :campos="['validade_dias' => 'Validade padrão (dias)', 'taxa_coleta' => 'Taxa de coleta', 'desconto_maximo' => 'Desconto máximo (%)', 'condicoes_comerciais' => 'Condições comerciais']" />
    </div>
</x-layout>
