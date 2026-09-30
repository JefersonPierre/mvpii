@props(['editar', 'historico', 'situacao' => null, 'ativo' => true])

{{-- Botões Editar, Histórico e Inativar/Reativar das listas do catálogo. --}}
<div class="flex justify-end gap-2">
    <a href="{{ $editar }}" class="botao botao-pequeno botao-secundario">Editar</a>
    <a href="{{ $historico }}" class="botao botao-pequeno botao-secundario">Histórico</a>
    @if ($situacao)
        <form method="POST" action="{{ $situacao }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="ativo" value="{{ $ativo ? 0 : 1 }}">
            @if ($ativo)
                <button type="submit" class="botao botao-pequeno botao-perigo">Inativar</button>
            @else
                <button type="submit" class="botao botao-pequeno bg-teal-50 text-teal-700 hover:bg-teal-100">Reativar</button>
            @endif
        </form>
    @endif
</div>
