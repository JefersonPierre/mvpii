Olá, {{ $nomeContato }}.

{{ count($laudos) === 1 ? 'Segue em anexo o laudo de análise' : 'Seguem em anexo os laudos de análise' }} da coleta realizada em {{ ($coleta->data_hora_coleta ?? $coleta->data)->format('d/m/Y') }} ({{ $coleta->orcamento->cliente->nome }}):

@foreach ($laudos as $l)
- {{ $l['numero'] }} – {{ $l['ponto'] }}: {{ $l['conclusao'] }}
@endforeach

A autenticidade de cada laudo pode ser conferida pelo QR Code impresso no documento.

{{ $laboratorio }}
@if (config('laboratorio.dados.telefone')){{ config('laboratorio.dados.telefone') }}
@endif
