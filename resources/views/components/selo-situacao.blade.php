@props(['ativo'])

<span @class(['selo', 'bg-green-100 text-green-800' => $ativo, 'bg-slate-200 text-slate-700' => ! $ativo])>
    {{ $ativo ? 'Ativo' : 'Inativo' }}
</span>
