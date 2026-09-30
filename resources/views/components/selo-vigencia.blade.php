@props(['legislacao'])

{{-- RN07: situação da vigência da legislação. --}}
@switch($legislacao->situacao())
    @case('Vigente')
        <span class="selo bg-green-100 text-green-800">Vigente desde {{ $legislacao->inicio_vigencia->format('d/m/Y') }}</span>
        @break
    @case('Futura')
        <span class="selo bg-sky-100 text-sky-800">Vigora a partir de {{ $legislacao->inicio_vigencia->format('d/m/Y') }}</span>
        @break
    @default
        <span class="selo bg-slate-200 text-slate-700">Encerrada em {{ $legislacao->fim_vigencia->format('d/m/Y') }}</span>
@endswitch
