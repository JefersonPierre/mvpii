{{-- UC06 – limite do parâmetro (RF16, RN06) --}}
@use('App\Models\Limite')
@use('App\Support\Numero')
@php
    $novo = ! $limite->exists;
    $titulo = $novo ? 'Adicionar limite' : 'Editar limite';
@endphp

<x-layout :titulo="$titulo">
    <div>
        <a href="{{ route('catalogo.legislacoes.show', $legislacao) }}" class="text-sm text-teal-700 hover:underline">← {{ $legislacao->nome }}</a>
        <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
    </div>

    <form method="POST" action="{{ $novo ? route('catalogo.limites.store', $legislacao) : route('catalogo.limites.update', $limite) }}"
          data-form-limite class="cartao max-w-2xl space-y-4" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="parametro_id" class="rotulo">Parâmetro *</label>
                <select id="parametro_id" name="parametro_id" @class(['campo', 'campo-erro' => $errors->has('parametro_id')])>
                    <option value="">Selecione…</option>
                    @foreach ($parametros as $p)
                        <option value="{{ $p->id }}" data-unidade="{{ $p->unidade }}" @selected((int) old('parametro_id', $limite->parametro_id) === $p->id)>
                            {{ $p->nome }}{{ $p->ativo ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                </select>
                @error('parametro_id') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="tipo_amostra_id" class="rotulo">Tipo de amostra *</label>
                <select id="tipo_amostra_id" name="tipo_amostra_id" @class(['campo', 'campo-erro' => $errors->has('tipo_amostra_id')])>
                    @if ($tipos->count() > 1)<option value="">Selecione…</option>@endif
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}" @selected((int) old('tipo_amostra_id', $limite->tipo_amostra_id) === $tipo->id)>{{ $tipo->nome }}</option>
                    @endforeach
                </select>
                @error('tipo_amostra_id') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset>
            <legend class="rotulo">Tipo de limite *</legend>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach (Limite::TIPOS as $valor => $rotulo)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="tipo" value="{{ $valor }}" @checked(old('tipo', $limite->tipo) === $valor) class="accent-teal-600"> {{ $rotulo }}
                    </label>
                @endforeach
            </div>
            @error('tipo') <p class="erro">{{ $message }}</p> @enderror
        </fieldset>

        <div class="grid gap-4 md:grid-cols-2">
            <div data-valor="minimo">
                <label for="valor_minimo" class="rotulo">Valor mínimo * <span class="font-normal text-slate-500" data-rotulo-unidade></span></label>
                <input id="valor_minimo" name="valor_minimo" inputmode="decimal" value="{{ old('valor_minimo', Numero::campo($limite->valor_minimo)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('valor_minimo')])>
                @error('valor_minimo') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div data-valor="maximo">
                <label for="valor_maximo" class="rotulo">Valor máximo * <span class="font-normal text-slate-500" data-rotulo-unidade></span></label>
                <input id="valor_maximo" name="valor_maximo" inputmode="decimal" value="{{ old('valor_maximo', Numero::campo($limite->valor_maximo)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('valor_maximo')])>
                @error('valor_maximo') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="observacao" class="rotulo">Observação</label>
            <input id="observacao" name="observacao" maxlength="255" value="{{ old('observacao', $limite->observacao) }}" placeholder="Ex.: Ausência em 100 mL"
                   @class(['campo', 'campo-erro' => $errors->has('observacao')])>
            @error('observacao') <p class="erro">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('catalogo.legislacoes.show', $legislacao) }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar limite</button>
        </div>
    </form>
</x-layout>
