{{-- UC08 – Elaborar orçamento (RF17, RN13, RN14, RN15) --}}
@use('App\Support\Numero')
@php
    $novo = ! $orcamento->exists;
    $titulo = $novo ? 'Novo orçamento' : 'Editar '.$orcamento->numeroComRevisao();

    // Itens: os enviados (após erro de validação) ou os já gravados. O preço de um item já gravado fica congelado (RN15).
    $gravados = $orcamento->exists ? $orcamento->itens->keyBy('id') : collect();
    $linhas = collect(old('itens', $gravados->map(fn ($i) => ['id' => $i->id, 'referencia' => $i->referencia(), 'quantidade' => $i->quantidade])->values()->all()))
        ->values()
        ->map(function ($linha, $i) use ($gravados, $errors) {
            $gravado = isset($linha['id']) ? $gravados->get((int) $linha['id']) : null;

            return [
                'id' => $linha['id'] ?? null,
                'referencia' => $linha['referencia'] ?? '',
                'quantidade' => $linha['quantidade'] ?? 1,
                'referenciaGravada' => $gravado?->referencia(),
                'precoGravado' => $gravado?->preco_unitario,
                'erro' => $errors->first("itens.$i.referencia") ?: $errors->first("itens.$i.quantidade"),
            ];
        });

    $dados = [
        'cliente' => $clienteJson,
        'contatoId' => (int) old('contato_id', $orcamento->contato_id) ?: null,
        'buscarClientes' => route('orcamentos.buscar-clientes'),
        'pacotes' => $pacotes->map(fn ($p) => [
            'referencia' => 'PACOTE:'.$p->id, 'nome' => $p->nome, 'preco' => $p->preco, 'tipoAmostraId' => $p->tipo_amostra_id,
            'parametros' => $p->parametros->pluck('nome')->implode(', '), 'ativo' => $p->ativo,
        ])->values(),
        'parametros' => $parametros->map(fn ($p) => [
            'referencia' => 'PARAMETRO:'.$p->id, 'nome' => $p->nome, 'preco' => $p->preco, 'ativo' => $p->ativo,
        ])->values(),
        'itens' => $linhas->values(),
    ];
@endphp

<x-layout :titulo="$titulo">
    <div>
        <a href="{{ $novo ? route('orcamentos.index') : route('orcamentos.show', $orcamento) }}" class="text-sm text-teal-700 hover:underline">
            ← {{ $novo ? 'Orçamentos' : $orcamento->numeroComRevisao() }}
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold">{{ $novo ? 'Novo orçamento' : $orcamento->numero() }}</h1>
            @unless ($novo)
                <span class="selo bg-slate-200 text-slate-700">Revisão {{ $orcamento->revisao }}</span>
                <x-selo-orcamento :situacao="$orcamento->situacao" />
            @endunless
        </div>
        <p class="text-sm text-slate-500">O número é gerado ao salvar. Os preços são copiados do catálogo e não mudam depois (RN15).</p>
    </div>

    <form method="POST" action="{{ $novo ? route('orcamentos.store') : route('orcamentos.update', $orcamento) }}"
          data-form-orcamento class="grid items-start gap-4 xl:grid-cols-[1fr_18rem]" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless
        <script type="application/json" data-dados-orcamento>@json($dados)</script>

        <div class="space-y-4">
            @if ($errors->any())
                <div class="alerta alerta-erro" role="alert">Confira os campos destacados.</div>
            @endif

            <div class="cartao grid gap-4 md:grid-cols-3">
                {{-- Cliente: busca por nome ou CPF/CNPJ (UC08 passo 1) --}}
                <div class="md:col-span-3" data-cliente>
                    <label for="busca-cliente" class="rotulo">Cliente *</label>
                    <input type="hidden" name="cliente_id" value="{{ old('cliente_id', $orcamento->cliente_id) }}">
                    <div data-cliente-escolhido hidden class="flex flex-wrap items-center gap-2 rounded-md border border-slate-300 px-3 py-2">
                        <span class="font-medium" data-cliente-nome></span>
                        <span class="font-mono text-xs text-slate-500" data-cliente-documento></span>
                        <span class="selo bg-amber-100 text-amber-800" data-cliente-interessado hidden>Interessado</span>
                        <button type="button" class="ml-auto text-sm text-teal-700 underline" data-trocar-cliente>Trocar</button>
                    </div>
                    <div data-cliente-busca class="relative">
                        <input id="busca-cliente" type="search" autocomplete="off" placeholder="Digite o nome ou o CPF/CNPJ"
                               @class(['campo', 'campo-erro' => $errors->has('cliente_id')])>
                        <ul data-cliente-resultados hidden
                            class="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"></ul>
                    </div>
                    @error('cliente_id') <p class="erro">{{ $message }}</p> @enderror
                    <p class="ajuda">
                        Cliente não encontrado?
                        <a href="{{ route('clientes.create', ['cadastro' => 'interessado', 'orcamento' => 'novo']) }}" class="text-teal-700 underline">Cadastro rápido de interessado</a>
                        (só nome e contato).
                    </p>
                </div>
                <div>
                    <label for="contato_id" class="rotulo">Contato (destinatário)</label>
                    <select id="contato_id" name="contato_id" @class(['campo', 'campo-erro' => $errors->has('contato_id')])></select>
                    @error('contato_id') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tipo_amostra_id" class="rotulo">Tipo de amostra *</label>
                    <select id="tipo_amostra_id" name="tipo_amostra_id" @class(['campo', 'campo-erro' => $errors->has('tipo_amostra_id')])>
                        <option value="">Selecione…</option>
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo->id }}" @selected((int) old('tipo_amostra_id', $orcamento->tipo_amostra_id) === $tipo->id)>{{ $tipo->nome }}</option>
                        @endforeach
                    </select>
                    @error('tipo_amostra_id') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="validade_dias" class="rotulo">Validade (dias) *</label>
                    <input id="validade_dias" name="validade_dias" type="number" min="1" max="365"
                           value="{{ old('validade_dias', $orcamento->validade_dias) }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('validade_dias')])>
                    <p class="ajuda">Contados a partir do envio.</p>
                    @error('validade_dias') <p class="erro">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Itens: pacotes e parâmetros avulsos (UC08 passo 3) --}}
            <div class="cartao space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">Itens</h2>
                    <div class="flex gap-2">
                        <button type="button" class="botao botao-pequeno botao-secundario" data-adicionar-item="PACOTE">Adicionar pacote</button>
                        <button type="button" class="botao botao-pequeno botao-secundario" data-adicionar-item="PARAMETRO">Adicionar parâmetro</button>
                    </div>
                </div>
                @error('itens') <p class="erro">{{ $message }}</p> @enderror
                <div class="hidden grid-cols-[minmax(12rem,1fr)_5rem_6.5rem_6.5rem_4.5rem] gap-2 text-xs font-semibold text-slate-600 md:grid">
                    <span>Item</span><span>Qtd. amostras</span><span class="text-right">Preço unitário</span><span class="text-right">Subtotal</span><span></span>
                </div>
                <div data-itens class="space-y-2"></div>
                <p data-sem-itens class="text-sm text-slate-500">Nenhum item. Adicione pacotes ou parâmetros avulsos.</p>
            </div>

            <div class="cartao grid gap-4 md:grid-cols-2">
                <div>
                    <label for="taxa_coleta" class="rotulo">Taxa de coleta (R$) *</label>
                    <input id="taxa_coleta" name="taxa_coleta" inputmode="decimal" value="{{ old('taxa_coleta', Numero::campo($orcamento->taxa_coleta, true)) }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('taxa_coleta')])>
                    @error('taxa_coleta') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="desconto_percentual" class="rotulo">Desconto (%)</label>
                    <input id="desconto_percentual" name="desconto_percentual" inputmode="decimal"
                           value="{{ old('desconto_percentual', Numero::decimal($orcamento->desconto_percentual)) }}"
                           data-desconto-maximo="{{ $descontoMaximo }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('desconto_percentual')])>
                    <p class="ajuda">Máximo permitido: {{ Numero::decimal($descontoMaximo) }}% (RN14).</p>
                    <p class="erro" data-aviso-desconto hidden></p>
                    @error('desconto_percentual') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="condicoes_pagamento" class="rotulo">Condições de pagamento e comerciais</label>
                    <textarea id="condicoes_pagamento" name="condicoes_pagamento" rows="3" maxlength="5000" class="campo">{{ old('condicoes_pagamento', $orcamento->condicoes_pagamento) }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label for="observacoes" class="rotulo">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="2" maxlength="2000" class="campo">{{ old('observacoes', $orcamento->observacoes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Resumo calculado na tela (o servidor recalcula ao salvar – RN13) --}}
        <aside class="cartao space-y-2 xl:sticky xl:top-4">
            <h2 class="text-lg font-semibold">Resumo</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Itens</dt><dd class="font-mono" data-resumo="itens">R$ 0,00</dd></div>
                <div class="flex justify-between"><dt>Taxa de coleta</dt><dd class="font-mono" data-resumo="taxa">R$ 0,00</dd></div>
                <div class="flex justify-between"><dt>Desconto (<span data-resumo="percentual">0</span>%)</dt><dd class="font-mono" data-resumo="desconto">− R$ 0,00</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd class="font-mono" data-resumo="total">R$ 0,00</dd></div>
            </dl>
            <div class="flex flex-col gap-2 pt-2">
                <button type="submit" class="botao botao-primario">Salvar rascunho</button>
                <a href="{{ $novo ? route('orcamentos.index') : route('orcamentos.show', $orcamento) }}" class="botao botao-secundario">Cancelar</a>
            </div>
        </aside>
    </form>
</x-layout>
