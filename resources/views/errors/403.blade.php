{{-- RN26 / RNF11: tela sem permissão para os perfis do usuário. --}}
@use('App\Support\Perfis')

@auth
    <x-layout titulo="Acesso negado">
        <div class="cartao max-w-xl space-y-3">
            <h1 class="text-2xl font-semibold">Acesso negado</h1>
            <p>{{ Perfis::MENSAGEM_ACESSO_NEGADO }}</p>
            <p class="text-sm text-slate-500">
                Seus perfis: {{ auth()->user()->nomesPerfis() ?: 'nenhum' }}. Se precisar deste acesso, fale com o
                administrador do sistema.
            </p>
            <a href="{{ route('inicio') }}" class="botao botao-secundario">Voltar ao início</a>
        </div>
    </x-layout>
@else
    <x-layouts.base titulo="Acesso negado">
        <main class="mx-auto max-w-xl p-8">
            <h1 class="text-2xl font-semibold">Acesso negado</h1>
            <p>{{ Perfis::MENSAGEM_ACESSO_NEGADO }}</p>
        </main>
    </x-layouts.base>
@endauth
