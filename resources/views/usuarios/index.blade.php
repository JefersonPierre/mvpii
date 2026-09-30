{{-- UC02 – Cadastrar usuários (RF03, RN10) --}}
<x-layout titulo="Usuários">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold">Usuários</h1>
        <a href="{{ route('usuarios.create') }}" class="botao botao-primario">Novo usuário</a>
    </div>

    <div class="cartao space-y-4">
        <form method="GET" action="{{ route('usuarios.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <label for="busca" class="rotulo">Buscar</label>
                <input id="busca" name="busca" type="search" value="{{ $busca }}" placeholder="Nome ou e-mail" class="campo">
            </div>
            <div>
                <label for="situacao" class="rotulo">Situação</label>
                <select id="situacao" name="situacao" class="campo" onchange="this.form.submit()">
                    @foreach (['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($situacao === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="botao botao-secundario">Filtrar</button>
        </form>

        @if ($usuarios->isEmpty())
            <p class="text-slate-500">Nenhum usuário encontrado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[640px]">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Situação</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $u)
                            <tr>
                                <td>{{ $u->nome }}</td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    <span @class(['selo', 'bg-teal-100 text-teal-800' => $u->ativo, 'bg-slate-200 text-slate-700' => ! $u->ativo])>
                                        {{ $u->ativo ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('usuarios.edit', $u) }}" class="botao botao-pequeno botao-secundario">Editar</a>
                                        <a href="{{ route('usuarios.historico', $u) }}" class="botao botao-pequeno botao-secundario">Histórico</a>
                                        @if ($u->ativo)
                                            @if ($u->is(auth()->user()))
                                                <button type="button" class="botao botao-pequeno botao-perigo" disabled
                                                        title="Você não pode inativar o seu próprio usuário.">Inativar</button>
                                            @else
                                                <button type="button" class="botao botao-pequeno botao-perigo"
                                                        data-abrir-dialogo="dialogo-inativar"
                                                        data-acao="{{ route('usuarios.situacao', $u) }}"
                                                        data-nome="{{ $u->nome }}">Inativar</button>
                                            @endif
                                        @else
                                            <form method="POST" action="{{ route('usuarios.situacao', $u) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="ativo" value="1">
                                                <button type="submit" class="botao botao-pequeno bg-teal-50 text-teal-700 hover:bg-teal-100">Reativar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $usuarios->links() }}
        @endif
    </div>

    {{-- RN10: confirmação antes de inativar --}}
    <dialog id="dialogo-inativar" class="dialogo" aria-labelledby="titulo-inativar">
        <form method="POST" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="ativo" value="0">
            <h2 id="titulo-inativar" class="text-lg font-semibold">Inativar usuário</h2>
            <p>
                <strong data-nome></strong> não poderá mais entrar no sistema. O cadastro e o histórico são mantidos
                e o usuário pode ser reativado depois.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao botao-secundario" data-fechar-dialogo>Cancelar</button>
                <button type="submit" class="botao botao-perigo-cheio">Inativar</button>
            </div>
        </form>
    </dialog>
</x-layout>
