{{ $mensagem }}

Orçamento {{ $orcamento->numeroComRevisao() }} – total de {{ \App\Support\Numero::moeda($orcamento->valor_total) }}, válido até {{ $validoAte->format('d/m/Y') }}.
O documento completo está em anexo (PDF).

{{ config('laboratorio.dados.nome') }}
@if (config('laboratorio.dados.telefone')){{ config('laboratorio.dados.telefone') }}
@endif
