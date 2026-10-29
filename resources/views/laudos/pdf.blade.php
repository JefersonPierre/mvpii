{{-- RF52: laudo da amostra em PDF (dompdf: HTML simples com CSS básico). Na pré-visualização não há número nem QR Code. --}}
@use('App\Support\Numero')
@use('App\Support\Documento')
@use('App\Services\EmissaoLaudos')
@php
    $coleta = $amostra->coleta;
    $cliente = $coleta->orcamento->cliente;
    $naoConformes = $resultados->where('conformidade', 'NAO_CONFORME');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Laudo {{ $previa ? '(prévia)' : $laudo->numeroComRevisao() }}</title>
    <style>
        @page { margin: 30px 38px 50px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1d2327; }
        h1 { font-size: 16px; margin: 0; color: #0f766e; }
        h2 { font-size: 11.5px; margin: 14px 0 5px; color: #0f766e; text-transform: uppercase; letter-spacing: 0.3px; }
        .cabecalho { border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 10px; }
        .cabecalho td { vertical-align: top; }
        .direita { text-align: right; }
        .centro { text-align: center; }
        .suave { color: #56626a; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados td { padding: 2px 4px; vertical-align: top; }
        table.dados td.rotulo { color: #56626a; width: 22%; }
        table.resultados { width: 100%; border-collapse: collapse; }
        table.resultados th { text-align: left; border-bottom: 1px solid #b9c0bb; padding: 4px 3px; font-size: 9px; color: #3b464d; }
        table.resultados td { border-bottom: 1px solid #e6e9e5; padding: 4px 3px; vertical-align: top; }
        tr.nc td { background: #fdecec; }
        .nc-texto { color: #b42318; font-weight: bold; }
        .ok-texto { color: #146c43; }
        .caixa { border: 1px solid #d9ddd8; padding: 7px 9px; margin-top: 4px; }
        .conclusao { border: 2px solid #0f766e; padding: 8px 10px; margin-top: 8px; font-size: 11px; }
        .conclusao.nc { border-color: #b42318; }
        .marca { position: fixed; top: 300px; left: 60px; font-size: 70px; color: #f1d5d5; transform: rotate(-30deg); }
        .rodape { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8.5px; color: #56626a; }
    </style>
</head>
<body>
    @if ($previa)<div class="marca">PRÉ-VISUALIZAÇÃO</div>@endif

    <table class="cabecalho" width="100%">
        <tr>
            <td>
                <h1>{{ $laboratorio['nome'] }}</h1>
                @foreach (['endereco', 'telefone', 'email', 'cnpj'] as $campo)
                    @if (filled($laboratorio[$campo] ?? null))<div class="suave">{{ $campo === 'cnpj' ? 'CNPJ '.$laboratorio[$campo] : $laboratorio[$campo] }}</div>@endif
                @endforeach
            </td>
            <td class="direita">
                <div style="font-size: 13px; font-weight: bold;">LAUDO DE ANÁLISE</div>
                <div class="mono" style="font-size: 13px;">{{ $previa ? 'LAU-'.$laudo->ano.'-____' : $laudo->numero() }}</div>
                <div>Revisão {{ $laudo->revisao }}</div>
                <div class="suave">Emitido em {{ $laudo->emitido_em->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    @if ($laudo->revisao > 1 && $laudo->substitui)
        <div class="caixa"><strong>Este laudo substitui o laudo {{ $laudo->substitui->numeroComRevisao() }}</strong>, emitido em {{ $laudo->substitui->emitido_em->format('d/m/Y') }}.
            @if ($laudo->motivo_retificacao) Motivo da retificação: {{ $laudo->motivo_retificacao }}@endif
        </div>
    @endif

    <h2>Cliente</h2>
    <table class="dados">
        <tr><td class="rotulo">Cliente</td><td><strong>{{ $cliente->nome }}</strong>
            @if ($cliente->documento) · {{ $cliente->tipo_pessoa === 'F' ? 'CPF' : 'CNPJ' }} {{ Documento::formatar($cliente->documento) }}@endif</td></tr>
        @if ($cliente->enderecoCompleto())<tr><td class="rotulo">Endereço</td><td>{{ $cliente->enderecoCompleto() }}</td></tr>@endif
    </table>

    <h2>Amostra</h2>
    <table class="dados">
        <tr><td class="rotulo">Código da amostra</td><td class="mono">{{ $amostra->codigo }}</td>
            <td class="rotulo">Tipo de amostra</td><td>{{ $coleta->orcamento->tipoAmostra->nome }}</td></tr>
        <tr><td class="rotulo">Ponto de coleta</td><td colspan="3">{{ $amostra->pontoColeta->identificacao }} – {{ $amostra->pontoColeta->enderecoCompleto() }}</td></tr>
        <tr><td class="rotulo">Coleta</td><td>{{ $coleta->data_hora_coleta?->format('d/m/Y H:i') }} · {{ $coleta->coletor->nome }}</td>
            <td class="rotulo">Temperatura na coleta</td><td>{{ $amostra->temperatura_coleta !== null ? Numero::decimal($amostra->temperatura_coleta).' °C' : '—' }}</td></tr>
        @if ($coleta->condicoes_tempo)<tr><td class="rotulo">Condições do tempo</td><td colspan="3">{{ $coleta->condicoes_tempo }}</td></tr>@endif
        <tr><td class="rotulo">Recebimento</td><td>{{ $amostra->recebida_em?->format('d/m/Y H:i') }} · {{ $amostra->recebidaPor?->nome }}</td>
            <td class="rotulo">Temperatura no recebimento</td><td>{{ $amostra->temperatura_recebimento !== null ? Numero::decimal($amostra->temperatura_recebimento).' °C' : '—' }}</td></tr>
        <tr><td class="rotulo">Legislação de referência</td><td colspan="3">{{ $amostra->legislacao?->nome ?? 'Não há legislação de referência vigente para este tipo de amostra na data da coleta: os resultados são apresentados sem comparação.' }}</td></tr>
    </table>

    <h2>Resultados</h2>
    <table class="resultados">
        <thead>
            <tr><th>Parâmetro</th><th>Método</th><th class="direita">Resultado</th><th>Unidade</th><th class="direita">LQ</th><th>Limite</th><th>Conformidade</th><th>Análise</th></tr>
        </thead>
        <tbody>
            @foreach ($resultados as $r)
                <tr @class(['nc' => $r->conformidade === 'NAO_CONFORME'])>
                    <td>{{ $r->parametro->nome }}@if ($r->origem === 'CAMPO')<span class="suave"> (campo)</span>@endif</td>
                    <td class="suave" style="font-size: 8.5px;">{{ $r->parametro->metodo }}</td>
                    <td class="direita mono">{{ $r->valorFormatado() }}</td>
                    <td>{{ $r->parametro->unidade }}</td>
                    <td class="direita mono">{{ Numero::decimal($r->parametro->limite_quantificacao) }}</td>
                    <td>{{ $amostra->legislacao ? $r->limiteFormatado() : '—' }}</td>
                    <td @class(['nc-texto' => $r->conformidade === 'NAO_CONFORME', 'ok-texto' => $r->conformidade === 'CONFORME'])>{{ $amostra->legislacao ? $r->nomeConformidade() : '—' }}</td>
                    <td>{{ $r->data_analise?->format('d/m/Y') }}</td>
                </tr>
                @if ($r->observacoes)<tr><td colspan="8" class="suave" style="font-size: 8.5px;">Obs.: {{ $r->observacoes }}</td></tr>@endif
            @endforeach
        </tbody>
    </table>
    <p class="suave" style="font-size: 8.5px;">LQ: limite de quantificação. "&lt; LQ": resultado abaixo do limite de quantificação do método. (campo): medido no local da coleta.</p>

    <div @class(['conclusao', 'nc' => $laudo->conclusao === 'NAO_CONFORME'])>
        <strong>Conclusão:</strong>
        @switch($laudo->conclusao)
            @case('CONFORME') A amostra está <strong>conforme</strong> aos limites da {{ $amostra->legislacao->nome }} para os parâmetros analisados. @break
            @case('NAO_CONFORME') A amostra está <strong>não conforme</strong> à {{ $amostra->legislacao->nome }} nos parâmetros: {{ $naoConformes->map(fn ($r) => $r->parametro->nome)->implode(', ') }}. @break
            @case('INCONCLUSIVA') Não é possível concluir a conformidade de todos os parâmetros com a {{ $amostra->legislacao->nome }}: veja os parâmetros indicados como inconclusivos. @break
            @default Não há legislação de referência para comparação dos resultados.
        @endswitch
    </div>

    @if ($config->observacoes_laudo)
        <h2>Observações</h2>
        <div style="white-space: pre-line;">{{ $config->observacoes_laudo }}</div>
    @endif
    <p class="suave" style="font-size: 8.5px;">Os resultados referem-se somente à amostra analisada. Este laudo só pode ser reproduzido por inteiro.</p>

    <table width="100%" style="margin-top: 18px;">
        <tr>
            <td style="vertical-align: bottom; width: 60%;">
                <div style="border-top: 1px solid #1d2327; width: 260px; padding-top: 3px;">
                    <strong>{{ $config->responsavel_tecnico }}</strong><br>
                    Responsável técnico · {{ $config->registro_profissional }}
                </div>
                @if ($amostra->liberadaPor)<div class="suave" style="margin-top: 4px;">Resultados liberados por {{ $amostra->liberadaPor->nome }} em {{ $amostra->liberada_em?->format('d/m/Y') }}.</div>@endif
            </td>
            <td class="direita" style="vertical-align: bottom;">
                @if ($qrCode)
                    <img src="{{ $qrCode }}" width="90" height="90" alt="QR Code de verificação">
                    <div class="suave">Verifique a autenticidade:</div>
                    <div class="mono">{{ EmissaoLaudos::formatarCodigo($laudo->codigo_verificacao) }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="rodape">
        {{ $previa ? 'Pré-visualização – sem valor de laudo' : $laudo->numeroComRevisao().' · código de verificação '.EmissaoLaudos::formatarCodigo($laudo->codigo_verificacao).' · '.route('laudos.verificar') }}
    </div>
</body>
</html>
