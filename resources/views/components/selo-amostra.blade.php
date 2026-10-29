@props(['situacao'])

{{-- RN33: situação da amostra (ampliada no Entregável 3 com as etapas dos resultados e do laudo). --}}
@php
    $cores = [
        'PREVISTA' => 'bg-sky-100 text-sky-800',
        'COLETADA' => 'bg-teal-100 text-teal-800',
        'NAO_COLETADA' => 'bg-amber-100 text-amber-800',
        'CANCELADA' => 'bg-slate-200 text-slate-600',
        'RECEBIDA' => 'bg-green-100 text-green-800',
        'REJEITADA' => 'bg-red-100 text-red-800',
        'EM_ANALISE' => 'bg-indigo-100 text-indigo-800',
        'EM_REVISAO' => 'bg-violet-100 text-violet-800',
        'LIBERADA' => 'bg-emerald-100 text-emerald-800',
        'LAUDO_EMITIDO' => 'bg-slate-800 text-white',
    ];
@endphp
<span class="selo whitespace-nowrap {{ $cores[$situacao] ?? '' }}">{{ \App\Models\Amostra::SITUACOES[$situacao] ?? $situacao }}</span>
