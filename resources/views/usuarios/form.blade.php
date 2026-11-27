{{-- UC02 – incluir e editar usuário (RF03, RN11) e UC13 – perfis do usuário (RF25, RN23, RN24) --}}
@use('App\Support\Perfis')
@php($novo = ! $usuario->exists)
@php($proprio = $usuario->is(auth()->user()))
@php($marcados = old('perfis', $novo ? [] : $usuario->listaPerfis()))

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

        <fieldset>
            <legend class="rotulo">Perfis</legend>
            <div class="space-y-2">
                @foreach (Perfis::NOMES as $codigo => $nome)
                    <label @class(['flex items-start gap-2 rounded-md border border-slate-200 px-3 py-2', 'opacity-60' => $proprio])>
                        <input type="checkbox" name="perfis[]" value="{{ $codigo }}" class="mt-1"
                               @checked(in_array($codigo, $marcados, true)) @disabled($proprio)>
                        <span>
                            <span class="block text-sm font-medium">{{ $nome }}</span>
                            <span class="block text-xs text-slate-500">{{ Perfis::DESCRICOES[$codigo] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($proprio)
                {{-- RN24: os próprios perfis não podem ser alterados; os atuais seguem no formulário. --}}
                @foreach ($usuario->listaPerfis() as $codigo)
                    <input type="hidden" name="perfis[]" value="{{ $codigo }}">
                @endforeach
                <p class="ajuda">Você não pode alterar os próprios perfis. Peça a outro administrador.</p>
            @else
                <p class="ajuda">As permissões do usuário são a soma das permissões dos perfis marcados.</p>
            @endif
            @error('perfis') <p class="erro">{{ $message }}</p> @enderror
            @error('perfis.*') <p class="erro">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">{{ $novo ? 'Cadastrar' : 'Salvar' }}</button>
        </div>
    </form>

    @php($aparelhos = $novo ? collect() : $usuario->dispositivos()->whereNull('revogado_em')->where('expira_em', '>', now())->get())
    @if ($aparelhos->isNotEmpty())
        {{-- RNF16: aparelhos com acesso ao aplicativo de campo --}}
        <div class="cartao max-w-lg space-y-3">
            <h2 class="text-lg font-semibold">Aparelhos conectados</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($aparelhos as $aparelho)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>{{ $aparelho->nome }} · acesso até {{ $aparelho->expira_em->format('d/m/Y H:i') }}
                            @if ($aparelho->ultimo_uso_em) · último uso {{ $aparelho->ultimo_uso_em->format('d/m/Y H:i') }} @endif</span>
                        <form method="POST" action="{{ route('usuarios.dispositivos.revogar', [$usuario, $aparelho]) }}" onsubmit="return confirm('Revogar o acesso deste aparelho?')">
                            @csrf
                            <button type="submit" class="botao botao-secundario botao-pequeno">Revogar acesso</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layout>
