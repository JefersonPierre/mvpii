{{-- UC15 – Consultar agenda de coletas (RF31, RN36) --}}
@use('App\Models\Coleta')
@php
    $nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $passo = $visao === 'dia' ? 1 : 7;
    $parametros = array_filter(['visao' => $visao, ...$filtros, 'coletor' => $somenteProprias ? null : $filtros['coletor']]);
    $link = fn (array $alterar) => route('coletas.index', array_filter([...$parametros, 'data' => $referencia->toDateString(), ...$alterar]));
    $cores = [
        'AGENDADA' => 'border-sky-600 bg-sky-50',
        'REALIZADA' => 'border-green-600 bg-green-50',
        'NAO_REALIZADA' => 'border-amber-500 bg-amber-50',
        'CANCELADA' => 'border-slate-400 bg-slate-100 text-slate-500',
    ];
    $titulo = $visao === 'dia'
        ? $nomesDias[$inicio->dayOfWeek].' '.$inicio->format('d/m/Y')
        : 'Semana de '.$inicio->format('d/m').' a '.$fim->format('d/m/Y');
@endphp

<x-layout titulo="Agenda de coletas">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">Agenda de coletas</h1>
            <p class="text-sm text-slate-500">
                {{ $titulo }}
                @if ($somenteProprias) · somente as coletas atribuídas a você @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $link(['data' => $referencia->copy()->subDays($passo)->toDateString()]) }}" class="botao botao-secundario" aria-label="Anterior">←</a>
            <a href="{{ $link(['data' => today()->toDateString()]) }}" class="botao botao-secundario">Hoje</a>
            <a href="{{ $link(['data' => $referencia->copy()->addDays($passo)->toDateString()]) }}" class="botao botao-secundario" aria-label="Próximo">→</a>
            <span class="ml-2 inline-flex rounded-md border border-slate-300 bg-white p-0.5">
                @foreach (['dia' => 'Dia', 'semana' => 'Semana'] as $valor => $rotulo)
                    <a href="{{ $link(['visao' => $valor]) }}" @class(['rounded px-3 py-1.5 text-sm', 'bg-teal-600 text-white' => $visao === $valor, 'text-slate-700 hover:bg-slate-50' => $visao !== $valor])>{{ $rotulo }}</a>
                @endforeach
            </span>
        </div>
    </div>

    <form method="GET" action="{{ route('coletas.index') }}" class="cartao flex flex-wrap items-end gap-3">
        <input type="hidden" name="visao" value="{{ $visao }}">
        <input type="hidden" name="data" value="{{ $referencia->toDateString() }}">
        @unless ($somenteProprias)
            <div class="min-w-48">
                <label for="coletor" class="rotulo">Coletor</label>
                <select id="coletor" name="coletor" class="campo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($coletores as $c)
                        <option value="{{ $c->id }}" @selected($filtros['coletor'] === $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
        @endunless
        <div class="min-w-48 flex-1">
            <label for="cliente" class="rotulo">Cliente</label>
            <input id="cliente" name="cliente" type="search" value="{{ $filtros['cliente'] }}" placeholder="Nome do cliente" class="campo">
        </div>
        <div>
            <label for="situacao" class="rotulo">Situação</label>
            <select id="situacao" name="situacao" class="campo" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach (Coleta::SITUACOES as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="botao botao-secundario">Filtrar</button>
    </form>

    @if ($coletas->isEmpty())
        <div class="cartao"><p class="text-slate-500">Nenhuma coleta {{ $visao === 'dia' ? 'neste dia' : 'nesta semana' }}.</p></div>
    @endif

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table @class(['w-full table-fixed border-collapse text-sm', 'min-w-[420px]' => $visao === 'dia', 'min-w-[900px]' => $visao === 'semana'])>
            <thead>
                <tr class="border-b border-slate-200">
                    <th class="w-20 p-2"></th>
                    @foreach ($dias as $dia)
                        <th @class(['p-2 text-left font-semibold', 'text-teal-700' => $dia->isToday()])>
                            <a href="{{ $link(['visao' => 'dia', 'data' => $dia->toDateString()]) }}" class="hover:underline">
                                {{ $nomesDias[$dia->dayOfWeek] }} {{ $dia->format('d/m') }}
                            </a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (Coleta::PERIODOS as $periodo => $nomePeriodo)
                    <tr class="border-b border-slate-100 align-top last:border-0">
                        <th class="p-2 text-left font-medium text-slate-500">{{ $nomePeriodo }}</th>
                        @foreach ($dias as $dia)
                            <td class="space-y-2 border-l border-slate-100 p-2">
                                @foreach ($coletas->filter(fn ($c) => $c->periodo === $periodo && $c->data->isSameDay($dia)) as $c)
                                    <a href="{{ route('coletas.show', $c) }}" class="block rounded-md border-l-4 px-2 py-1.5 hover:shadow {{ $cores[$c->situacao] }}">
                                        <span @class(['block font-semibold', 'line-through' => $c->situacao === Coleta::CANCELADA])>{{ $c->orcamento->cliente->nome }}</span>
                                        <span class="block text-xs">
                                            @if ($c->horarioFormatado()){{ $c->horarioFormatado() }} · @endif
                                            @if ($c->situacao === Coleta::AGENDADA)
                                                {{ $c->amostras_count === 1 ? '1 amostra' : $c->amostras_count.' amostras' }}
                                            @else
                                                {{ $c->nomeSituacao() }}
                                            @endif
                                            @unless ($somenteProprias) · {{ $c->coletor->nome }} @endunless
                                        </span>
                                    </a>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="flex flex-wrap gap-3 text-xs text-slate-500">
        @foreach (Coleta::SITUACOES as $valor => $rotulo)
            <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm border-l-4 {{ $cores[$valor] }}"></span>{{ $rotulo }}</span>
        @endforeach
    </p>
</x-layout>
