@props(['situacao'])

{{-- RN31: situação da coleta. --}}
@php
    $cores = [
        'AGENDADA' => 'bg-sky-100 text-sky-800',
        'REALIZADA' => 'bg-green-100 text-green-800',
        'NAO_REALIZADA' => 'bg-amber-100 text-amber-800',
        'CANCELADA' => 'bg-slate-200 text-slate-600 line-through',
    ];
@endphp
<span class="selo {{ $cores[$situacao] ?? '' }}">{{ \App\Models\Coleta::SITUACOES[$situacao] ?? $situacao }}</span>
