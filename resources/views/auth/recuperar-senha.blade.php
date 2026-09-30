{{-- RF02 – Recuperar senha por e-mail --}}
<x-layout-acesso titulo="Recuperar senha">
    @unless (session('sucesso'))
        <form method="POST" action="{{ route('recuperar-senha') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="rotulo">E-mail cadastrado</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                       @class(['campo', 'campo-erro' => $errors->has('email')])>
                @error('email') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="botao botao-primario w-full">Enviar link</button>
        </form>
    @endunless
    <a href="{{ route('login') }}" class="text-sm text-teal-700 hover:underline">Voltar para o login</a>
</x-layout-acesso>
