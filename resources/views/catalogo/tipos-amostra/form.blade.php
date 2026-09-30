{{-- UC05 1a – tipo de amostra (RF12, RN05) --}}
@php($novo = ! $tipo->exists)

<x-layout :titulo="$novo ? 'Novo tipo de amostra' : 'Editar tipo de amostra'">
    <div>
        <a href="{{ route('catalogo.tipos-amostra.index') }}" class="text-sm text-teal-700 hover:underline">← Tipos de amostra</a>
        <h1 class="text-2xl font-semibold">{{ $novo ? 'Novo tipo de amostra' : 'Editar tipo de amostra' }}</h1>
    </div>

    <form method="POST" action="{{ $novo ? route('catalogo.tipos-amostra.store') : route('catalogo.tipos-amostra.update', $tipo) }}"
          class="cartao max-w-lg space-y-4">
        @csrf
        @unless ($novo) @method('PUT') @endunless
        <div>
            <label for="nome" class="rotulo">Nome *</label>
            <input id="nome" name="nome" required maxlength="80" autofocus value="{{ old('nome', $tipo->nome) }}"
                   @class(['campo', 'campo-erro' => $errors->has('nome')])>
            @error('nome') <p class="erro">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="descricao" class="rotulo">Descrição</label>
            <input id="descricao" name="descricao" maxlength="255" value="{{ old('descricao', $tipo->descricao) }}"
                   @class(['campo', 'campo-erro' => $errors->has('descricao')])>
            @error('descricao') <p class="erro">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('catalogo.tipos-amostra.index') }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</x-layout>
