Olá, {{ $nomeContato }}.

@switch($tipo)
@case(\App\Mail\ColetaMail::CONFIRMACAO)
Confirmamos o agendamento da coleta de amostras referente ao orçamento {{ $coleta->orcamento->numero() }}.
@break
@case(\App\Mail\ColetaMail::REAGENDAMENTO)
A coleta de amostras referente ao orçamento {{ $coleta->orcamento->numero() }} foi reagendada.
@if ($coleta->motivo)Motivo: {{ $coleta->motivo }}
@endif
@break
@case(\App\Mail\ColetaMail::CANCELAMENTO)
A coleta de amostras referente ao orçamento {{ $coleta->orcamento->numero() }}, prevista para {{ $coleta->quando() }}, foi cancelada.
Motivo: {{ $coleta->motivo }}
Entraremos em contato para combinar uma nova data, se for o caso.
@break
@endswitch
@unless ($tipo === \App\Mail\ColetaMail::CANCELAMENTO)

Data: {{ $coleta->data->format('d/m/Y') }} – {{ $coleta->nomePeriodo() }}{{ $coleta->horarioFormatado() ? ', a partir das '.$coleta->horarioFormatado() : '' }}
Responsável pela coleta: {{ $coleta->coletor->nome }}
Pontos de coleta:
@foreach ($coleta->amostras->groupBy('ponto_coleta_id') as $amostras)
- {{ $amostras->first()->pontoColeta->identificacao }} ({{ $amostras->count() === 1 ? '1 amostra' : $amostras->count().' amostras' }})
@endforeach

Pedimos que o acesso aos pontos de coleta esteja liberado no horário combinado.
@endunless

{{ config('laboratorio.dados.nome') }}
@if (config('laboratorio.dados.telefone')){{ config('laboratorio.dados.telefone') }}
@endif
