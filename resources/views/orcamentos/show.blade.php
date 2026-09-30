{{-- UC08–UC11 – orçamento: detalhes, ações conforme a situação e histórico --}}
@use('App\Models\Orcamento')
@use('App\Support\Numero')

<x-layout :titulo="$orcamento->numeroComRevisao()">
    <a href="{{ route('orcamentos.index') }}" class="text-sm text-teal-700 hover:underline">← Orçamentos</a>

    <div class="cartao space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-mono text-2xl font-semibold">{{ $orcamento->numero() }}</h1>
                    <span class="selo bg-slate-200 text-slate-700">Revisão {{ $orcamento->revisao }}</span>
                    <x-selo-orcamento :situacao="$orcamento->situacao" />
                </div>
                <p class="text-sm text-slate-500">
                    Criado em {{ $orcamento->criado_em->format('d/m/Y') }}{{ $orcamento->criadoPor ? ' por '.$orcamento->criadoPor->nome : '' }}
                    @if ($orcamento->data_envio)
                        · enviado em {{ $orcamento->data_envio->format('d/m/Y H:i') }}{{ $orcamento->envio_email ? ' para '.$orcamento->envio_email : ' (envio manual)' }}
                        · válido até {{ $orcamento->valido_ate->format('d/m/Y') }}
                    @else
                        · validade de {{ $orcamento->validade_dias }} dias a partir do envio
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($orcamento->editavel())
                    <a href="{{ route('orcamentos.edit', $orcamento) }}" class="botao botao-secundario">Editar</a>
                    <a href="{{ route('orcamentos.envio', $orcamento) }}" class="botao botao-primario">Enviar</a>
                @else
                    <a href="{{ route('orcamentos.pdf', $orcamento) }}" target="_blank" class="botao botao-secundario">Ver PDF</a>
                @endif
                @if ($orcamento->situacao === Orcamento::ENVIADO)
                    <button type="button" class="botao botao-primario" data-abrir-dialogo="dialogo-resposta">Registrar resposta</button>
                @endif
                @if ($orcamento->permiteNovaRevisao())
                    <form method="POST" action="{{ route('orcamentos.revisao', $orcamento) }}">
                        @csrf
                        <button type="submit" class="botao botao-secundario">Nova revisão</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('orcamentos.duplicar', $orcamento) }}">
                    @csrf
                    <button type="submit" class="botao botao-secundario">Duplicar</button>
                </form>
            </div>
        </div>

        @if ($orcamento->situacao === Orcamento::RECUSADO)
            <div class="alerta border-red-200 bg-red-50 text-red-800">
                Recusado em {{ $orcamento->data_resposta->format('d/m/Y') }}. Motivo: {{ $orcamento->nomeMotivoRecusa() }}{{ $orcamento->motivo_detalhe ? ' – '.$orcamento->motivo_detalhe : '' }}.
            </div>
        @elseif ($orcamento->situacao === Orcamento::APROVADO)
            <div class="alerta alerta-sucesso">
                Aprovado em {{ $orcamento->data_resposta->format('d/m/Y') }}. Pronto para o agendamento da coleta (Entregável 2).
                <a href="{{ route('clientes.show', [$orcamento->cliente, 'aba' => 'pontos']) }}" class="font-medium underline">Ver pontos de coleta do cliente</a>
            </div>
        @elseif ($orcamento->situacao === Orcamento::EXPIRADO)
            <div class="alerta border-amber-200 bg-amber-50 text-amber-900">
                A validade venceu em {{ $orcamento->valido_ate->format('d/m/Y') }} sem resposta do cliente. Para retomar, crie uma nova revisão (RN17).
            </div>
        @elseif ($orcamento->situacao === Orcamento::SUBSTITUIDO)
            <div class="alerta border-slate-200 bg-slate-50 text-slate-700">Esta revisão foi substituída por uma mais recente.</div>
        @endif

        <dl class="grid gap-x-8 gap-y-3 text-sm md:grid-cols-3">
            <div>
                <dt class="text-slate-500">Cliente</dt>
                <dd>
                    <a href="{{ route('clientes.show', $orcamento->cliente) }}" class="text-teal-700 hover:underline">{{ $orcamento->cliente->nome }}</a>
                    <x-selo-cliente :cliente="$orcamento->cliente" />
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Contato</dt>
                <dd>{{ $orcamento->contato ? $orcamento->contato->nome.($orcamento->contato->email ? ' · '.$orcamento->contato->email : '') : '—' }}</dd>
            </div>
            <div><dt class="text-slate-500">Tipo de amostra</dt><dd>{{ $orcamento->tipoAmostra->nome }}</dd></div>
        </dl>
    </div>

    <div class="grid items-start gap-4 lg:grid-cols-[1fr_20rem]">
        <div class="cartao space-y-4">
            <h2 class="text-lg font-semibold">Itens</h2>
            <div class="overflow-x-auto">
                <table class="tabela min-w-[560px]">
                    <thead><tr><th>Item</th><th class="text-right">Qtd. amostras</th><th class="text-right">Preço unitário</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                        @foreach ($orcamento->itens as $item)
                            <tr>
                                <td>
                                    {{ $item->tipo === 'PARAMETRO' ? 'Parâmetro avulso: ' : '' }}{{ $item->descricao }}
                                    @if ($item->parametros_incluidos)<div class="text-xs text-slate-500">Inclui: {{ $item->parametros_incluidos }}</div>@endif
                                </td>
                                <td class="text-right font-mono">{{ $item->quantidade }}</td>
                                <td class="text-right font-mono">{{ Numero::moeda($item->preco_unitario) }}</td>
                                <td class="text-right font-mono">{{ Numero::moeda($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($orcamento->condicoes_pagamento)
                <div><h3 class="text-sm font-semibold">Condições de pagamento e comerciais</h3><p class="text-sm whitespace-pre-line">{{ $orcamento->condicoes_pagamento }}</p></div>
            @endif
            @if ($orcamento->observacoes)
                <div><h3 class="text-sm font-semibold">Observações</h3><p class="text-sm whitespace-pre-line">{{ $orcamento->observacoes }}</p></div>
            @endif
        </div>

        <aside class="cartao space-y-2">
            <h2 class="text-lg font-semibold">Resumo</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Itens</dt><dd class="font-mono">{{ Numero::moeda($orcamento->subtotal_itens) }}</dd></div>
                <div class="flex justify-between"><dt>Taxa de coleta</dt><dd class="font-mono">{{ Numero::moeda($orcamento->taxa_coleta) }}</dd></div>
                <div class="flex justify-between"><dt>Desconto ({{ Numero::decimal($orcamento->desconto_percentual) }}%)</dt><dd class="font-mono">− {{ Numero::moeda($orcamento->valor_desconto) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd class="font-mono">{{ Numero::moeda($orcamento->valor_total) }}</dd></div>
            </dl>
            @if ($revisoes->count() > 1)
                <div class="border-t border-slate-200 pt-3">
                    <h3 class="mb-1 text-sm font-semibold">Revisões</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach ($revisoes as $r)
                            <li class="flex items-center justify-between gap-2">
                                @if ($r->is($orcamento))
                                    <span class="font-mono font-semibold">r{{ $r->revisao }}</span>
                                @else
                                    <a href="{{ route('orcamentos.show', $r) }}" class="font-mono text-teal-700 hover:underline">r{{ $r->revisao }}</a>
                                @endif
                                <x-selo-orcamento :situacao="$r->situacao" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>

    @if ($orcamento->situacao === Orcamento::ENVIADO)
        {{-- UC10 – Registrar resposta (RF19, RN18, RN19) --}}
        <dialog id="dialogo-resposta" class="dialogo max-w-lg" aria-labelledby="titulo-resposta"
                @if ($errors->hasAny(['resultado', 'data_resposta', 'motivo_recusa', 'motivo_detalhe']) || old('resultado')) data-abrir-ao-carregar @endif>
            <form method="POST" action="{{ route('orcamentos.resposta', $orcamento) }}" class="space-y-4" novalidate>
                @csrf
                <h2 id="titulo-resposta" class="text-lg font-semibold">Registrar resposta · {{ $orcamento->numeroComRevisao() }}</h2>
                <fieldset>
                    <legend class="rotulo">Resposta do cliente *</legend>
                    <div class="flex gap-4 text-sm">
                        @foreach ([Orcamento::APROVADO => 'Aprovado', Orcamento::RECUSADO => 'Recusado'] as $valor => $rotulo)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="resultado" value="{{ $valor }}" @checked(old('resultado') === $valor) class="accent-teal-600"> {{ $rotulo }}
                            </label>
                        @endforeach
                    </div>
                    @error('resultado') <p class="erro">{{ $message }}</p> @enderror
                </fieldset>
                <div>
                    <label for="data_resposta" class="rotulo">Data da resposta *</label>
                    <input id="data_resposta" name="data_resposta" type="date" max="{{ today()->toDateString() }}"
                           value="{{ old('data_resposta', today()->toDateString()) }}"
                           @class(['campo', 'campo-erro' => $errors->has('data_resposta')])>
                    @error('data_resposta') <p class="erro">{{ $message }}</p> @enderror
                </div>

                @unless ($orcamento->cliente->cadastroCompleto())
                    {{-- RN18 / UC10 2a --}}
                    <div class="alerta border-amber-200 bg-amber-50 text-amber-900">
                        {{ $orcamento->cliente->interessado ? 'Este cliente ainda é um interessado.' : 'O cadastro do cliente está incompleto.' }}
                        Complete CPF/CNPJ e endereço para aprovar.
                        <a href="{{ route('clientes.edit', [$orcamento->cliente, 'orcamento' => $orcamento->id]) }}" class="font-medium underline">Completar cadastro</a>
                    </div>
                @endunless

                <fieldset class="space-y-3 border-t border-slate-200 pt-3">
                    <legend class="rotulo">Se recusado</legend>
                    <div>
                        <label for="motivo_recusa" class="rotulo">Motivo da recusa</label>
                        <select id="motivo_recusa" name="motivo_recusa" @class(['campo', 'campo-erro' => $errors->has('motivo_recusa')])>
                            <option value=""></option>
                            @foreach (Orcamento::MOTIVOS_RECUSA as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('motivo_recusa') === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('motivo_recusa') <p class="erro">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="motivo_detalhe" class="rotulo">Detalhe (obrigatório em "Outro")</label>
                        <input id="motivo_detalhe" name="motivo_detalhe" maxlength="255" value="{{ old('motivo_detalhe') }}"
                               @class(['campo', 'campo-erro' => $errors->has('motivo_detalhe')])>
                        @error('motivo_detalhe') <p class="erro">{{ $message }}</p> @enderror
                    </div>
                </fieldset>

                <div class="flex justify-end gap-2">
                    <button type="button" class="botao botao-secundario" data-fechar-dialogo>Cancelar</button>
                    <button type="submit" class="botao botao-primario">Confirmar</button>
                </div>
            </form>
        </dialog>
    @endif

    <div class="cartao space-y-3">
        <h2 class="text-lg font-semibold">Histórico</h2>
        <x-tabela-historico :registros="$registros" :campos="[
            'cliente' => 'Cliente', 'contato' => 'Contato', 'tipo_amostra' => 'Tipo de amostra', 'itens' => 'Itens',
            'taxa_coleta' => 'Taxa de coleta', 'desconto_percentual' => 'Desconto', 'valor_total' => 'Total',
            'validade_dias' => 'Validade (dias)', 'condicoes_pagamento' => 'Condições', 'observacoes' => 'Observações',
            'situacao' => 'Situação', 'data_envio' => 'Envio', 'envio_email' => 'Enviado para', 'valido_ate' => 'Válido até',
            'data_resposta' => 'Data da resposta', 'motivo_recusa' => 'Motivo da recusa',
        ]" />
    </div>
</x-layout>
