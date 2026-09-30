@props(['situacao'])

{{-- RF19: situação do orçamento. --}}
@php
    $cores = [
        'RASCUNHO' => 'bg-slate-200 text-slate-700',
        'ENVIADO' => 'bg-sky-100 text-sky-800',
        'APROVADO' => 'bg-green-100 text-green-800',
        'RECUSADO' => 'bg-red-100 text-red-800',
        'EXPIRADO' => 'bg-amber-100 text-amber-800',
        'SUBSTITUIDO' => 'bg-slate-100 text-slate-500',
    ];
@endphp
<span class="selo {{ $cores[$situacao] ?? '' }}">{{ \App\Models\Orcamento::SITUACOES[$situacao] ?? $situacao }}</span>
