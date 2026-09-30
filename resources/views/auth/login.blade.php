{{-- UC01 – Autenticar usuário (RF01, RN09, RN10) --}}
<x-layout-acesso titulo="Entrar">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        @error('email')
            <div class="alerta alerta-erro" role="alert">{{ $message }}</div>
        @enderror
        @error('senha')
            <div class="alerta alerta-erro" role="alert">{{ $message }}</div>
        @enderror
        <div>
            <label for="email" class="rotulo">E-mail</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}" class="campo">
        </div>
        <div>
            <label for="senha" class="rotulo">Senha</label>
            <input id="senha" name="senha" type="password" required autocomplete="current-password"
                   @class(['campo', 'campo-erro' => $errors->any()])>
        </div>
        <button type="submit" class="botao botao-primario w-full">Entrar</button>
    </form>
    <a href="{{ route('recuperar-senha') }}" class="text-sm text-teal-700 hover:underline">Esqueci minha senha</a>
</x-layout-acesso>
