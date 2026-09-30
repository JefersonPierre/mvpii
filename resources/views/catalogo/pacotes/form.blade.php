{{-- UC05 1b – pacote de análise (RF14, RN05) --}}
@use('App\Models\Parametro')
@use('App\Support\Numero')
@php
    $novo = ! $pacote->exists;
    $selecionados = array_map('intval', old('parametros', $atuais));
@endphp

<x-layout :titulo="$novo ? 'Novo pacote' : 'Editar pacote'">
    <div>
        <a href="{{ route('catalogo.pacotes.index') }}" class="text-sm text-teal-700 hover:underline">← Pacotes</a>
        <h1 class="text-2xl font-semibold">{{ $novo ? 'Novo pacote de análise' : 'Editar pacote de análise' }}</h1>
    </div>

    <form method="POST" action="{{ $novo ? route('catalogo.pacotes.store') : route('catalogo.pacotes.update', $pacote) }}"
          data-form-pacote class="cartao max-w-3xl space-y-5" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="nome" class="rotulo">Nome *</label>
                <input id="nome" name="nome" maxlength="120" autofocus value="{{ old('nome', $pacote->nome) }}" placeholder="Ex.: Potabilidade básica"
                       @class(['campo', 'campo-erro' => $errors->has('nome')])>
                @error('nome') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="tipo_amostra_id" class="rotulo">Tipo de amostra *</label>
                <select id="tipo_amostra_id" name="tipo_amostra_id" @class(['campo', 'campo-erro' => $errors->has('tipo_amostra_id')])>
                    <option value="">Selecione…</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}" @selected((int) old('tipo_amostra_id', $pacote->tipo_amostra_id) === $tipo->id)>
                            {{ $tipo->nome }}{{ $tipo->ativo ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                </select>
                @error('tipo_amostra_id') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="preco" class="rotulo">Preço do pacote (R$) *</label>
                <input id="preco" name="preco" inputmode="decimal" value="{{ old('preco', Numero::campo($pacote->preco, true)) }}" placeholder="0,00"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('preco')])>
                <p class="ajuda" data-soma-avulsa aria-live="polite"></p>
                @error('preco') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-base font-semibold">Parâmetros incluídos *</legend>
            @error('parametros') <p class="erro">{{ $message }}</p> @enderror
            @error('parametros.*') <p class="erro">{{ $message }}</p> @enderror
            @forelse ($parametros as $categoria => $lista)
                <div>
                    <p class="mb-1 text-sm font-medium text-slate-600">{{ Parametro::CATEGORIAS[$categoria] ?? $categoria }}</p>
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($lista as $p)
                            <label class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
                                <input type="checkbox" name="parametros[]" value="{{ $p->id }}" data-preco="{{ $p->preco }}"
                                       @checked(in_array($p->id, $selecionados, true)) class="accent-teal-600">
                                <span class="flex-1">{{ $p->nome }}{{ $p->ativo ? '' : ' (inativo)' }}</span>
                                <span class="font-mono text-xs text-slate-500">{{ Numero::moeda($p->preco) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">
                    Nenhum parâmetro ativo. <a href="{{ route('catalogo.parametros.create') }}" class="underline">Cadastre os parâmetros</a> primeiro.
                </p>
            @endforelse
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ route('catalogo.pacotes.index') }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</x-layout>
