{{-- RF32: ficha de coleta em PDF (dompdf), uma página por ponto de coleta, legível em preto e branco --}}
@php
    $cliente = $coleta->orcamento->cliente;
    $contato = $cliente->contatoPrincipal;
    $porPonto = $coleta->amostras->groupBy('ponto_coleta_id');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ficha de coleta – {{ $coleta->data->format('d/m/Y') }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 12px; margin: 14px 0 6px; text-transform: uppercase; }
        .cabecalho { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
        .cabecalho td { vertical-align: top; }
        .direita { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        table.grade { width: 100%; border-collapse: collapse; }
        table.grade th, table.grade td { border: 1px solid #000; padding: 6px 5px; vertical-align: top; text-align: left; }
        table.grade th { font-size: 10px; background: #eee; }
        .linha { border-bottom: 1px solid #000; height: 18px; }
        .caixa { border: 1px solid #000; padding: 6px 8px; }
        .quebra { page-break-after: always; }
    </style>
</head>
<body>
@foreach ($porPonto as $amostras)
    @php($ponto = $amostras->first()->pontoColeta)
    <div @if (! $loop->last) class="quebra" @endif>
        <table class="cabecalho" width="100%">
            <tr>
                <td>
                    <h1>FICHA DE COLETA</h1>
                    <div>{{ $laboratorio['nome'] }}</div>
                </td>
                <td class="direita">
                    <div style="font-size: 13px;"><strong>{{ $coleta->quando() }}</strong></div>
                    <div>Coletor: {{ $coleta->coletor->nome }}</div>
                    <div class="mono">Orçamento {{ $coleta->orcamento->numero() }} · Ponto {{ $loop->iteration }}/{{ $loop->count }}</div>
                </td>
            </tr>
        </table>

        <h2>Cliente e local</h2>
        <div><strong>{{ $cliente->nome }}</strong> · {{ $coleta->orcamento->tipoAmostra->nome }}</div>
        @if ($contato)
            <div>Contato: {{ $contato->nome }}{{ $contato->telefone ? ' · '.$contato->telefoneFormatado() : '' }}{{ $contato->email ? ' · '.$contato->email : '' }}</div>
        @endif
        <div style="margin-top: 6px;"><strong>Ponto de coleta: {{ $ponto->identificacao }}</strong></div>
        <div>Endereço: {{ $ponto->enderecoCompleto() ?: $cliente->enderecoCompleto() ?: '—' }}</div>
        @if ($ponto->referencia)<div>Referência: {{ $ponto->referencia }}</div>@endif
        @if ($ponto->latitude !== null)<div>Coordenadas: {{ $ponto->latitude }}, {{ $ponto->longitude }}</div>@endif

        @if ($coleta->instrucoes)
            <h2>Instruções</h2>
            <div class="caixa">{!! nl2br(e($coleta->instrucoes)) !!}</div>
        @endif

        <h2>Amostras</h2>
        <table class="grade">
            <thead>
                <tr>
                    <th style="width: 23%;">Código (anotar no frasco)</th>
                    <th>Itens a analisar</th>
                    <th style="width: 11%;">Coletada? (S/N)</th>
                    <th style="width: 11%;">Hora</th>
                    <th style="width: 12%;">Temp. (°C)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($amostras as $amostra)
                    <tr>
                        <td class="mono" style="font-size: 12px;"><strong>{{ $amostra->codigo }}</strong></td>
                        <td>{{ $amostra->descricaoItens() }}</td>
                        <td></td><td></td><td></td>
                    </tr>
                    <tr><td colspan="5" style="height: 26px;"><span style="font-size: 9px;">Observações / motivo se não coletada:</span></td></tr>
                @endforeach
            </tbody>
        </table>

        <h2>Registro em campo</h2>
        <table width="100%">
            <tr>
                <td style="width: 50%;">Data e hora da coleta: <div class="linha"></div></td>
                <td style="width: 50%; padding-left: 16px;">Condições do tempo: <div class="linha"></div></td>
            </tr>
            <tr>
                <td style="padding-top: 18px;">Assinatura do coletor: <div class="linha"></div></td>
                <td style="padding-top: 18px; padding-left: 16px;">Responsável no local: <div class="linha"></div></td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
