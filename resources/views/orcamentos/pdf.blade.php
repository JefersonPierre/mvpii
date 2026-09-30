{{-- RF18: orçamento em PDF (gerado pelo dompdf: HTML simples com CSS básico) --}}
@use('App\Support\Numero')
@use('App\Support\Documento')
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Orçamento {{ $orcamento->numeroComRevisao() }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1d2327; }
        h1 { font-size: 18px; margin: 0; color: #0f766e; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .cabecalho { border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 14px; }
        .cabecalho td { vertical-align: top; }
        .direita { text-align: right; }
        .suave { color: #56626a; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        table.itens { width: 100%; border-collapse: collapse; }
        table.itens th { text-align: left; border-bottom: 1px solid #b9c0bb; padding: 5px 4px; font-size: 10px; color: #3b464d; }
        table.itens td { border-bottom: 1px solid #e6e9e5; padding: 6px 4px; vertical-align: top; }
        table.resumo { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 10px; }
        table.resumo td { padding: 3px 4px; }
        table.resumo tr.total td { border-top: 1px solid #1d2327; font-size: 13px; font-weight: bold; padding-top: 6px; }
        .caixa { border: 1px solid #d9ddd8; padding: 8px 10px; margin-top: 4px; }
        .rodape { position: fixed; bottom: -12px; left: 0; right: 0; font-size: 9px; color: #56626a; text-align: center; }
    </style>
</head>
<body>
    <table class="cabecalho" width="100%">
        <tr>
            <td>
                <h1>{{ $laboratorio['nome'] }}</h1>
                @foreach (['endereco', 'telefone', 'email', 'cnpj'] as $campo)
                    @if (filled($laboratorio[$campo] ?? null))<div class="suave">{{ $campo === 'cnpj' ? 'CNPJ '.$laboratorio[$campo] : $laboratorio[$campo] }}</div>@endif
                @endforeach
            </td>
            <td class="direita">
                <div style="font-size: 14px; font-weight: bold;">ORÇAMENTO</div>
                <div class="mono" style="font-size: 13px;">{{ $orcamento->numero() }}</div>
                <div>Revisão {{ $orcamento->revisao }}</div>
                <div class="suave">Emitido em {{ ($orcamento->data_envio ?? now())->format('d/m/Y') }}</div>
                <div><strong>Válido até {{ $validoAte->format('d/m/Y') }}</strong></div>
            </td>
        </tr>
    </table>

    <h2>Cliente</h2>
    <div><strong>{{ $orcamento->cliente->nome }}</strong>
        @if ($orcamento->cliente->documento) · {{ $orcamento->cliente->tipo_pessoa === 'F' ? 'CPF' : 'CNPJ' }} {{ Documento::formatar($orcamento->cliente->documento) }}@endif
    </div>
    @if ($orcamento->cliente->enderecoCompleto())<div>{{ $orcamento->cliente->enderecoCompleto() }}</div>@endif
    @if ($orcamento->contato)
        <div>A/C {{ $orcamento->contato->nome }}{{ $orcamento->contato->email ? ' · '.$orcamento->contato->email : '' }}{{ $orcamento->contato->telefone ? ' · '.$orcamento->contato->telefoneFormatado() : '' }}</div>
    @endif

    <h2>Análises – {{ $orcamento->tipoAmostra->nome }}</h2>
    <table class="itens">
        <thead>
            <tr><th>Item</th><th class="direita">Qtd. amostras</th><th class="direita">Preço unitário</th><th class="direita">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach ($orcamento->itens as $item)
                <tr>
                    <td>
                        {{ $item->descricao }}
                        @if ($item->parametros_incluidos)<div class="suave" style="font-size: 9px;">Inclui: {{ $item->parametros_incluidos }}</div>@endif
                    </td>
                    <td class="direita mono">{{ $item->quantidade }}</td>
                    <td class="direita mono">{{ Numero::moeda($item->preco_unitario) }}</td>
                    <td class="direita mono">{{ Numero::moeda($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="resumo">
        <tr><td>Análises</td><td class="direita mono">{{ Numero::moeda($orcamento->subtotal_itens) }}</td></tr>
        <tr><td>Taxa de coleta</td><td class="direita mono">{{ Numero::moeda($orcamento->taxa_coleta) }}</td></tr>
        @if ((float) $orcamento->desconto_percentual > 0)
            <tr><td>Desconto ({{ Numero::decimal($orcamento->desconto_percentual) }}%)</td><td class="direita mono">− {{ Numero::moeda($orcamento->valor_desconto) }}</td></tr>
        @endif
        <tr class="total"><td>Total</td><td class="direita mono">{{ Numero::moeda($orcamento->valor_total) }}</td></tr>
    </table>

    @if ($orcamento->condicoes_pagamento)
        <h2>Condições comerciais</h2>
        <div class="caixa">{!! nl2br(e($orcamento->condicoes_pagamento)) !!}</div>
    @endif
    @if ($orcamento->observacoes)
        <h2>Observações</h2>
        <div class="caixa">{!! nl2br(e($orcamento->observacoes)) !!}</div>
    @endif

    <div class="rodape">{{ $laboratorio['nome'] }} · Orçamento {{ $orcamento->numeroComRevisao() }} · válido até {{ $validoAte->format('d/m/Y') }}</div>
</body>
</html>
