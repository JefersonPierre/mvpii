@props(['registros', 'campos' => []])

{{-- RN08 / RF24: histórico de alterações (quem, quando, valor anterior e novo). --}}
@php
    $acoes = [
        'INCLUSAO' => ['Inclusão', 'bg-teal-100 text-teal-800'],
        'ALTERACAO' => ['Alteração', 'bg-sky-100 text-sky-800'],
        'INATIVACAO' => ['Inativação', 'bg-red-100 text-red-800'],
        'REATIVACAO' => ['Reativação', 'bg-green-100 text-green-800'],
    ];
    // Campos booleanos gravados como "true"/"false" e mostrados em palavras.
    $booleanos = [
        'ativo' => ['Ativo', 'Inativo'],
        'interessado' => ['Interessado', 'Cliente'],
    ];
    $valor = function (?string $campo, ?string $v) use ($booleanos) {
        if ($v === null) {
            return '—';
        }
        if (isset($booleanos[$campo])) {
            return $v === 'true' ? $booleanos[$campo][0] : $booleanos[$campo][1];
        }
        return match ($campo) {
            'tipo_pessoa' => $v === 'F' ? 'Física' : 'Jurídica',
            'documento' => \App\Support\Documento::formatar($v),
            'cep' => \App\Support\Documento::formatarCep($v),
            'categoria' => \App\Models\Parametro::CATEGORIAS[$v] ?? $v,
            'preco' => \App\Support\Numero::moeda($v),
            'limite_quantificacao', 'latitude', 'longitude' => \App\Support\Numero::decimal($v),
            'inicio_vigencia', 'fim_vigencia' => \Illuminate\Support\Carbon::parse($v)->format('d/m/Y'),
            default => $v,
        };
    };
@endphp

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
                        <td>
                            @if ($r->prefixo_campo)<span class="text-slate-500">{{ $r->prefixo_campo }} –</span>@endif
                            {{ $campos[$r->campo] ?? $r->campo ?? '—' }}
                        </td>
                        <td>{{ $valor($r->campo, $r->valor_anterior) }}</td>
                        <td>{{ $valor($r->campo, $r->valor_novo) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $registros->links() }}
@endif
