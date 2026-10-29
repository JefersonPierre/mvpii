@use('App\Support\Numero')

<x-layout titulo="Início">
    <h1 class="text-2xl font-semibold">Olá, {{ auth()->user()->nome }}</h1>
    @if (auth()->user()->temPerfil(\App\Support\Perfis::COLETOR))
        {{-- RF38: o coletor usa o sistema no celular, também sem conexão. --}}
        <div class="cartao flex flex-wrap items-center justify-between gap-3">
            <p>No celular, abra <strong>Coletas do dia</strong> para ver o roteiro e registrar as coletas, mesmo sem internet. Instale na tela inicial pelo menu do navegador.</p>
            <a href="{{ route('campo') }}" class="botao botao-primario">Coletas do dia</a>
        </div>
    @endif
    @if ($conflitos->isNotEmpty())
        <div class="cartao space-y-2 border-amber-300 bg-amber-50">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-amber-900">Conflitos de sincronização ({{ $conflitos->count() }})</h2>
                <a href="{{ route('conflitos.index') }}" class="text-sm text-teal-700 hover:underline">Resolver</a>
            </div>
            @foreach ($conflitos as $c)
                <a href="{{ route('conflitos.show', $c) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-amber-100">
                    <span class="text-sm"><strong>{{ $c->coleta->orcamento->cliente->nome }}</strong> · {{ $c->usuario->nome }}: {{ $c->motivo_conflito }}</span>
                    <span class="text-xs whitespace-nowrap text-slate-500">{{ $c->recebida_em->format('d/m H:i') }}</span>
                </a>
            @endforeach
        </div>
    @endif
    @if ($pendencias)
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($pendencias as [$rotulo, $quantidade, $link])
                <a href="{{ $link }}" class="cartao flex items-center justify-between gap-3 hover:border-teal-500">
                    <span class="font-medium">{{ $rotulo }}</span>
                    <span @class(['text-2xl font-semibold', 'text-teal-700' => $quantidade, 'text-slate-400' => ! $quantidade])>{{ $quantidade }}</span>
                </a>
            @endforeach
        </div>
    @endif
    @can('orcamentos')
    <div class="cartao">
        <p class="mb-4">O atendimento começa pelo orçamento. Use os atalhos abaixo:</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('orcamentos.create') }}" class="botao botao-primario">Novo orçamento</a>
            <a href="{{ route('clientes.index') }}" class="botao botao-secundario">Pesquisar cliente</a>
        </div>
    </div>

    <div class="grid items-start gap-4 lg:grid-cols-2">
        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Aguardando resposta do cliente ({{ $aguardando->count() }})</h2>
            @forelse ($aguardando as $o)
                @php($dias = (int) today()->diffInDays($o->valido_ate, false))
                <a href="{{ route('orcamentos.show', $o) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                    <span>
                        <span class="font-mono text-sm text-teal-800">{{ $o->numeroComRevisao() }}</span>
                        <span class="block text-sm">{{ $o->cliente->nome }}</span>
                    </span>
                    <span class="text-right text-sm whitespace-nowrap">
                        <span class="font-mono">{{ Numero::moeda($o->valor_total) }}</span>
                        <span @class(['block text-xs', 'font-semibold text-amber-700' => $dias <= 3, 'text-slate-500' => $dias > 3])>
                            {{ $dias === 0 ? 'vence hoje' : ($dias === 1 ? 'vence amanhã' : "vence em {$dias} dias") }}
                        </span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-500">Nenhum orçamento aguardando resposta.</p>
            @endforelse
        </div>

        <div class="cartao space-y-3">
            <h2 class="text-lg font-semibold">Rascunhos ({{ $rascunhos->count() }})</h2>
            @forelse ($rascunhos as $o)
                <a href="{{ route('orcamentos.show', $o) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                    <span>
                        <span class="font-mono text-sm text-teal-800">{{ $o->numeroComRevisao() }}</span>
                        <span class="block text-sm">{{ $o->cliente->nome }}</span>
                    </span>
                    <span class="text-right text-sm whitespace-nowrap">
                        <span class="font-mono">{{ Numero::moeda($o->valor_total) }}</span>
                        <span class="block text-xs text-slate-500">alterado em {{ $o->atualizado_em->format('d/m') }}</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-500">Nenhum rascunho pendente.</p>
            @endforelse
        </div>
    </div>
    @endcan

    @if (Gate::any(['coletas.agenda', 'amostras.receber']))
    <div class="grid items-start gap-4 lg:grid-cols-2">
        @can('coletas.agenda')
            <div class="cartao space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ auth()->user()->veTodasAsColetas() ? 'Próximas coletas' : 'Minhas próximas coletas' }} ({{ $proximas->count() }})</h2>
                    <a href="{{ route('coletas.index') }}" class="text-sm text-teal-700 hover:underline">Agenda</a>
                </div>
                @forelse ($proximas as $c)
                    <a href="{{ route('coletas.show', $c) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                        <span>
                            <span class="text-sm font-medium">{{ $c->orcamento->cliente->nome }}</span>
                            <span class="block text-xs text-slate-500">{{ $c->amostras_count === 1 ? '1 amostra' : $c->amostras_count.' amostras' }} · {{ $c->coletor->nome }}</span>
                        </span>
                        <span @class(['text-right text-sm whitespace-nowrap', 'font-semibold text-teal-700' => $c->data->isToday()])>
                            {{ $c->data->isToday() ? 'hoje' : $c->data->format('d/m') }} · {{ $c->nomePeriodo() }}
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma coleta agendada.</p>
                @endforelse
            </div>
        @endcan

        @can('amostras.receber')
            <div class="cartao space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">Amostras a receber ({{ $aReceber->count() }})</h2>
                    <a href="{{ route('amostras.receber') }}" class="text-sm text-teal-700 hover:underline">Receber amostras</a>
                </div>
                @forelse ($aReceber as $a)
                    <a href="{{ route('amostras.receber', ['codigo' => $a->codigo]) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                        <span>
                            <span class="font-mono text-sm text-teal-800">{{ $a->codigo }}</span>
                            <span class="block text-xs text-slate-500">{{ $a->coleta->orcamento->cliente->nome }} · {{ $a->pontoColeta->identificacao }}</span>
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma amostra aguardando recebimento.</p>
                @endforelse
            </div>
        @endcan

        @if ($naoRealizadas->isNotEmpty())
            <div class="cartao space-y-3">
                <h2 class="text-lg font-semibold">Coletas não realizadas para reagendar ({{ $naoRealizadas->count() }})</h2>
                @foreach ($naoRealizadas as $c)
                    <a href="{{ route('coletas.show', $c) }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                        <span class="text-sm font-medium">{{ $c->orcamento->cliente->nome }}</span>
                        <span class="text-sm text-slate-500">{{ $c->data->format('d/m') }} · {{ $c->nomePeriodo() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    @endif
</x-layout>
