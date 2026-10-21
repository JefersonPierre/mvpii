<?php

namespace App\Http\Controllers;

use App\Models\Coleta;
use App\Services\AgendamentoColetas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// UC15 – Consultar agenda de coletas (RF31, RN36): por dia ou semana, com filtros por coletor, cliente e situação.
class AgendaController extends Controller
{
    public function __invoke(Request $request, AgendamentoColetas $agendamento): View
    {
        $usuario = $request->user();
        $visao = $request->query('visao') === 'dia' ? 'dia' : 'semana';
        $referencia = rescue(fn () => Carbon::parse((string) $request->query('data', today()->toDateString()))->startOfDay(), today(), false);
        [$inicio, $fim] = $visao === 'dia'
            ? [$referencia->copy(), $referencia->copy()]
            : [$referencia->copy()->startOfWeek(Carbon::MONDAY), $referencia->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)];

        // RN36: quem só tem o perfil Coletor vê somente as coletas atribuídas a ele.
        $somenteProprias = ! $usuario->veTodasAsColetas();
        $filtros = [
            'coletor' => $somenteProprias ? $usuario->id : ((int) $request->query('coletor') ?: null),
            'cliente' => trim((string) $request->query('cliente')),
            'situacao' => array_key_exists($request->query('situacao'), Coleta::SITUACOES) ? $request->query('situacao') : '',
        ];

        $coletas = Coleta::with(['orcamento.cliente', 'coletor'])->withCount('amostras')
            ->whereDate('data', '>=', $inicio)->whereDate('data', '<=', $fim)
            ->when($filtros['coletor'], fn ($q, $c) => $q->where('coletor_id', $c))
            ->when($filtros['cliente'], fn ($q, $c) => $q->whereHas('orcamento.cliente', fn ($q) => $q->whereRaw('LOWER(nome) LIKE ?', ['%'.mb_strtolower($c).'%'])))
            ->when($filtros['situacao'], fn ($q, $s) => $q->where('situacao', $s))
            ->orderBy('data')->orderBy('periodo')->orderByRaw('horario IS NULL')->orderBy('horario')->orderBy('id')
            ->get();

        // A semana mostra de segunda a sábado; o domingo só aparece se tiver coleta.
        $dias = collect(range(0, $visao === 'dia' ? 0 : 6))->map(fn ($n) => $inicio->copy()->addDays($n))
            ->reject(fn (Carbon $d) => $visao === 'semana' && $d->isSunday() && ! $coletas->contains(fn ($c) => $c->data->isSameDay($d)));

        return view('coletas.index', [
            'visao' => $visao,
            'referencia' => $referencia,
            'inicio' => $inicio,
            'fim' => $fim,
            'dias' => $dias,
            'coletas' => $coletas,
            'filtros' => $filtros,
            'somenteProprias' => $somenteProprias,
            'coletores' => $somenteProprias ? collect() : $agendamento->coletores(),
        ]);
    }
}
