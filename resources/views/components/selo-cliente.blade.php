@props(['cliente'])

{{-- RF05: interessados aparecem com etiqueta própria. --}}
@if ($cliente->interessado)
    <span class="selo bg-amber-100 text-amber-800">Interessado</span>
@else
    <span class="selo bg-teal-100 text-teal-800">Cliente</span>
@endif
