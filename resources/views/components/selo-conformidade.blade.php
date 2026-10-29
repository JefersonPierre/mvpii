@props(['valor', 'pequeno' => false])

{{-- RN52: conformidade de um resultado ou conclusão da amostra. --}}
@php
    $nomes = \App\Models\Resultado::CONFORMIDADES + \App\Models\Amostra::CONFORMIDADES;
    $cores = [
        'CONFORME' => 'bg-green-100 text-green-800',
        'NAO_CONFORME' => 'bg-red-100 text-red-800',
        'INCONCLUSIVO' => 'bg-amber-100 text-amber-800',
        'INCONCLUSIVA' => 'bg-amber-100 text-amber-800',
        'SEM_LIMITE' => 'bg-slate-100 text-slate-600',
        'SEM_LEGISLACAO' => 'bg-slate-100 text-slate-600',
    ];
@endphp
@if ($valor)
    <span class="selo whitespace-nowrap {{ $cores[$valor] ?? '' }}">{{ $nomes[$valor] ?? $valor }}</span>
@endif
