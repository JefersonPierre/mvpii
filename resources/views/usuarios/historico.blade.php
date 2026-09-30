{{-- RN08: histórico de alterações do usuário (quem, quando, valor anterior e novo) --}}
@php
    $acoes = [
        'INCLUSAO' => ['Inclusão', 'bg-teal-100 text-teal-800'],
        'ALTERACAO' => ['Alteração', 'bg-sky-100 text-sky-800'],
        'INATIVACAO' => ['Inativação', 'bg-red-100 text-red-800'],
        'REATIVACAO' => ['Reativação', 'bg-green-100 text-green-800'],
    ];
    $campos = ['nome' => 'Nome', 'email' => 'E-mail', 'ativo' => 'Situação'];
    $valor = fn (?string $campo, ?string $v) => match (true) {
        $v === null => '—',
        $campo === 'ativo' => $v === 'true' ? 'Ativo' : 'Inativo',
        default => $v,
    };
@endphp

<x-layout titulo="Histórico – {{ $usuario->nome }}">
    <div>
        <a href="{{ route('usuarios.index') }}" class="text-sm text-teal-700 hover:underline">← Usuários</a>
        <h1 class="text-2xl font-semibold">Histórico – {{ $usuario->nome }}</h1>
    </div>

    <div class="cartao space-y-4">
        @if ($registros->isEmpty())
            <p class="text-slate-500">Nenhuma alteração registrada.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Data e hora</th>
                            <th>Responsável</th>
                            <th>Ação</th>
                            <th>Campo</th>
                            <th>Valor anterior</th>
                            <th>Valor novo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registros as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->data_hora->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $r->responsavel?->nome ?? '—' }}</td>
                                <td><span class="selo {{ $acoes[$r->acao][1] ?? '' }}">{{ $acoes[$r->acao][0] ?? $r->acao }}</span></td>
                                <td>{{ $campos[$r->campo] ?? $r->campo ?? '—' }}</td>
                                <td>{{ $valor($r->campo, $r->valor_anterior) }}</td>
                                <td>{{ $valor($r->campo, $r->valor_novo) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $registros->links() }}
        @endif
    </div>
</x-layout>
