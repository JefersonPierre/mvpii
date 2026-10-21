@props(['situacao'])

{{-- RN33: situação da amostra. --}}
@php
    $cores = [
        'PREVISTA' => 'bg-sky-100 text-sky-800',
        'COLETADA' => 'bg-teal-100 text-teal-800',
        'NAO_COLETADA' => 'bg-amber-100 text-amber-800',
        'CANCELADA' => 'bg-slate-200 text-slate-600',
        'RECEBIDA' => 'bg-green-100 text-green-800',
        'REJEITADA' => 'bg-red-100 text-red-800',
    ];
@endphp
<span class="selo whitespace-nowrap {{ $cores[$situacao] ?? '' }}">{{ \App\Models\Amostra::SITUACOES[$situacao] ?? $situacao }}</span>
