<?php

namespace App\Services;

use App\Models\Coleta;
use App\Models\Configuracao;
use App\Support\Roteiro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// MOD11 – UC20 Consultar roteiro de coletas (RF44–RF46, RN47, RN48).
class Roteirizacao
{
    /**
     * Coletas do coletor no dia (sem as canceladas), na ordem de visita: a alterada manualmente ou a sugerida (RN47).
     *
     * @return Collection<int, Coleta>
     */
    public function coletasDoDia(int $coletorId, string $data, bool $somenteAgendadas = false): Collection
    {
        $coletas = Coleta::with(['orcamento.cliente', 'amostras.pontoColeta', 'amostras.itens'])
            ->where('coletor_id', $coletorId)->whereDate('data', $data)
            ->when($somenteAgendadas, fn ($q) => $q->where('situacao', Coleta::AGENDADA),
                fn ($q) => $q->where('situacao', '!=', Coleta::CANCELADA))
            ->get();

        return $this->ordenar($coletas);
    }

    /**
     * @param  Collection<int, Coleta>  $coletas
     * @return Collection<int, Coleta>
     */
    public function ordenar(Collection $coletas): Collection
    {
        $config = Configuracao::atual();
        $paradas = $coletas->map(function (Coleta $c) {
            $ponto = $c->pontoNoMapa();

            return ['chave' => $c->id, 'periodo' => $c->periodo, 'horario' => $c->horarioFormatado(),
                'lat' => $ponto?->latitude, 'lng' => $ponto?->longitude];
        })->values()->all();
        $sugerida = array_flip(Roteiro::ordemSugerida($paradas, $config->laboratorio_latitude, $config->laboratorio_longitude));

        // RN47: a ordem alterada manualmente vale para aquele dia; coletas novas entram depois, na ordem sugerida.
        return $coletas->sortBy(fn (Coleta $c) => [$c->ordem_visita ?? PHP_INT_MAX, $sugerida[$c->id]])->values();
    }

    /**
     * UC20 1a: grava a nova ordem do dia.
     *
     * @param  list<int>  $ids  coletas na nova ordem
     */
    public function definirOrdem(int $coletorId, string $data, array $ids): void
    {
        DB::transaction(function () use ($coletorId, $data, $ids) {
            $doDia = Coleta::where('coletor_id', $coletorId)->whereDate('data', $data)->pluck('id')->all();
            if (array_diff($ids, $doDia)) {
                throw ValidationException::withMessages(['ordem' => 'A ordem inclui coletas de outro dia ou de outro coletor.']);
            }
            foreach (array_values($ids) as $posicao => $id) {
                Coleta::whereKey($id)->update(['ordem_visita' => $posicao + 1]);
            }
        });
    }

    /**
     * Move uma coleta uma posição para cima ou para baixo na ordem atual (botões da tela, acessíveis ao toque).
     */
    public function mover(Coleta $coleta, string $direcao): void
    {
        $ids = $this->coletasDoDia($coleta->coletor_id, $coleta->data->toDateString())->modelKeys();
        $i = array_search($coleta->id, $ids, true);
        $j = $direcao === 'subir' ? $i - 1 : $i + 1;
        if ($i === false || ! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->definirOrdem($coleta->coletor_id, $coleta->data->toDateString(), $ids);
    }

    /**
     * RN48: links de navegação para as coletas ainda não visitadas, na ordem do roteiro.
     *
     * @param  Collection<int, Coleta>  $coletas  já ordenadas
     * @return array{google: list<string>, waze: string|null}
     */
    public function links(Collection $coletas): array
    {
        $config = Configuracao::atual();
        $paradas = $coletas->where('situacao', Coleta::AGENDADA)
            ->map(fn (Coleta $c) => $c->pontoNoMapa())->filter()
            ->map(fn ($p) => [$p->latitude, $p->longitude])->values()->all();

        return [
            'google' => $paradas ? Roteiro::linksGoogleMaps($paradas, $config->laboratorio_latitude, $config->laboratorio_longitude) : [],
            'waze' => $paradas ? Roteiro::linkWaze($paradas[0][0], $paradas[0][1]) : null,
        ];
    }

    /**
     * Distância em linha reta de cada coleta até a anterior (a primeira, até o laboratório), em metros.
     *
     * @param  Collection<int, Coleta>  $coletas  já ordenadas
     * @return array<int, int|null> coleta => metros
     */
    public function distancias(Collection $coletas): array
    {
        $config = Configuracao::atual();
        $anterior = $config->temCoordenadasLaboratorio() ? [$config->laboratorio_latitude, $config->laboratorio_longitude] : null;
        $distancias = [];
        foreach ($coletas as $coleta) {
            $ponto = $coleta->pontoNoMapa();
            $distancias[$coleta->id] = $ponto && $anterior
                ? (int) round(Roteiro::distanciaMetros($anterior[0], $anterior[1], $ponto->latitude, $ponto->longitude)) : null;
            if ($ponto) {
                $anterior = [$ponto->latitude, $ponto->longitude];
            }
        }

        return $distancias;
    }
}
