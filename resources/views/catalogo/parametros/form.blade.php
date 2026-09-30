{{-- UC05 – parâmetro de análise (RF13, RN05) --}}
@use('App\Models\Parametro')
@use('App\Support\Numero')
@php($novo = ! $parametro->exists)

<x-layout :titulo="$novo ? 'Novo parâmetro' : 'Editar parâmetro'">
    <div>
        <a href="{{ route('catalogo.parametros.index') }}" class="text-sm text-teal-700 hover:underline">← Parâmetros</a>
        <h1 class="text-2xl font-semibold">{{ $novo ? 'Novo parâmetro' : 'Editar parâmetro' }}</h1>
    </div>

    <form method="POST" action="{{ $novo ? route('catalogo.parametros.store') : route('catalogo.parametros.update', $parametro) }}"
          class="cartao max-w-2xl space-y-4" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="nome" class="rotulo">Nome *</label>
                <input id="nome" name="nome" maxlength="120" autofocus value="{{ old('nome', $parametro->nome) }}" placeholder="Ex.: Turbidez"
                       @class(['campo', 'campo-erro' => $errors->has('nome')])>
                @error('nome') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="unidade" class="rotulo">Unidade de medida *</label>
                <input id="unidade" name="unidade" maxlength="30" value="{{ old('unidade', $parametro->unidade) }}" placeholder="Ex.: mg/L, uT, NMP/100 mL"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('unidade')])>
                <p class="ajuda">Para parâmetros sem unidade, como o pH, use "adimensional".</p>
                @error('unidade') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="categoria" class="rotulo">Categoria *</label>
                <select id="categoria" name="categoria" @class(['campo', 'campo-erro' => $errors->has('categoria')])>
                    @foreach (Parametro::CATEGORIAS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('categoria', $parametro->categoria) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
                @error('categoria') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="metodo" class="rotulo">Método de análise</label>
                <input id="metodo" name="metodo" maxlength="150" value="{{ old('metodo', $parametro->metodo) }}" placeholder="Ex.: SMWW 2130 B – Nefelométrico"
                       @class(['campo', 'campo-erro' => $errors->has('metodo')])>
                @error('metodo') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="limite_quantificacao" class="rotulo">Limite de quantificação</label>
                <input id="limite_quantificacao" name="limite_quantificacao" inputmode="decimal"
                       value="{{ old('limite_quantificacao', Numero::campo($parametro->limite_quantificacao)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('limite_quantificacao')])>
                @error('limite_quantificacao') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="preco" class="rotulo">Preço unitário (R$) *</label>
                <input id="preco" name="preco" inputmode="decimal" value="{{ old('preco', Numero::campo($parametro->preco, true)) }}" placeholder="0,00"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('preco')])>
                @error('preco') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('catalogo.parametros.index') }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</x-layout>
