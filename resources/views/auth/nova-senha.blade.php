{{-- RF02 – criar nova senha a partir do link recebido por e-mail --}}
<x-layout-acesso titulo="Criar nova senha">
    <form method="POST" action="{{ route('nova-senha') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ old('token', $token) }}">
        @error('token')
            <div class="alerta alerta-erro" role="alert">{{ $message }}</div>
        @enderror
        <div>
            <label for="senha" class="rotulo">Nova senha</label>
            <input id="senha" name="senha" type="password" required autofocus autocomplete="new-password"
                   @class(['campo', 'campo-erro' => $errors->has('senha')])>
            <p class="ajuda">Mínimo de 8 caracteres, com letras e números.</p>
            @error('senha') <p class="erro">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="senha_confirmation" class="rotulo">Confirme a nova senha</label>
            <input id="senha_confirmation" name="senha_confirmation" type="password" required autocomplete="new-password" class="campo">
        </div>
        <button type="submit" class="botao botao-primario w-full">Salvar nova senha</button>
    </form>
    <a href="{{ route('login') }}" class="text-sm text-teal-700 hover:underline">Ir para o login</a>
</x-layout-acesso>
