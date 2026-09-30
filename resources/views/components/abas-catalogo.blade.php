{{-- Navegação entre as seções do catálogo técnico (MOD04). --}}
@php
    $abas = [
        ['Tipos de amostra', 'catalogo.tipos-amostra.index', 'catalogo.tipos-amostra.*'],
        ['Parâmetros', 'catalogo.parametros.index', 'catalogo.parametros.*'],
        ['Pacotes', 'catalogo.pacotes.index', 'catalogo.pacotes.*'],
        ['Legislações e limites', 'catalogo.legislacoes.index', 'catalogo.legislacoes.*'],
    ];
@endphp
<div>
    <p class="text-sm text-slate-500">Catálogo técnico</p>
    <nav class="mt-1 flex flex-wrap gap-1 border-b border-slate-200" aria-label="Seções do catálogo">
        @foreach ($abas as [$rotulo, $rota, $padrao])
            @php($ativa = request()->routeIs($padrao))
            <a href="{{ route($rota) }}"
               @class([
                   '-mb-px border-b-2 px-3 py-2 text-sm',
                   'border-teal-600 font-semibold text-slate-900' => $ativa,
                   'border-transparent text-slate-500 hover:text-slate-800' => ! $ativa,
               ])
               @if ($ativa) aria-current="page" @endif>{{ $rotulo }}</a>
        @endforeach
    </nav>
</div>
