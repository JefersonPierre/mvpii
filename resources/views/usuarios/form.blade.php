{{-- UC02 – incluir e editar usuário (RF03, RN11) --}}
@php($novo = ! $usuario->exists)

<x-layout :titulo="$novo ? 'Novo usuário' : 'Editar usuário'">
    <div>
        <a href="{{ route('usuarios.index') }}" class="text-sm text-teal-700 hover:underline">← Usuários</a>
        <h1 class="text-2xl font-semibold">{{ $novo ? 'Novo usuário' : 'Editar usuário' }}</h1>
    </div>

    <form method="POST" action="{{ $novo ? route('usuarios.store') : route('usuarios.update', $usuario) }}"
          class="cartao max-w-lg space-y-4">
        @csrf
        @unless ($novo) @method('PUT') @endunless

        <div>
            <label for="nome" class="rotulo">Nome</label>
            <input id="nome" name="nome" required maxlength="120" autofocus value="{{ old('nome', $usuario->nome) }}"
                   @class(['campo', 'campo-erro' => $errors->has('nome')])>
            @error('nome') <p class="erro">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="rotulo">E-mail</label>
            <input id="email" name="email" type="email" required maxlength="150" value="{{ old('email', $usuario->email) }}"
                   @class(['campo', 'campo-erro' => $errors->has('email')])>
            @if ($novo)
                <p class="ajuda">O usuário recebe neste e-mail o link para criar a própria senha.</p>
            @endif
            @error('email') <p class="erro">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">{{ $novo ? 'Cadastrar' : 'Salvar' }}</button>
        </div>
    </form>
</x-layout>
