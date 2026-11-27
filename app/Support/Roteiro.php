<?php

namespace App\Support;

// MOD11 – Roteirização: ordem de visita (RN47), distâncias em linha reta e links de navegação (RN48).
final class Roteiro
{
    /** RN48: limite de paradas do link do Google Maps. */
    public const PARADAS_POR_TRECHO = 9;

    /** Distância em linha reta (haversine), em metros. */
    public static function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $raio = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $raio * asin(min(1, sqrt($a)));
    }

    /**
     * RN47: parte da origem (laboratório) e segue sempre para a parada ainda não visitada mais próxima, em linha reta.
     * As da manhã vêm antes das da tarde; as com horário combinado são encaixadas na ordem do horário (só a próxima com
     * horário concorre com as sem horário); as sem coordenadas ficam no fim, pela ordem do horário.
     *
     * @param  list<array{chave: int|string, periodo: string, horario: string|null, lat: float|null, lng: float|null}>  $paradas
     * @return list<int|string> chaves na ordem sugerida
     */
    public static function ordemSugerida(array $paradas, ?float $origemLat, ?float $origemLng): array
    {
        $porHorario = fn (array $a, array $b) => [$a['horario'] ?? '99', $a['chave']] <=> [$b['horario'] ?? '99', $b['chave']];
        $comCoordenadas = array_values(array_filter($paradas, fn ($p) => $p['lat'] !== null && $p['lng'] !== null));
        $semCoordenadas = array_values(array_filter($paradas, fn ($p) => $p['lat'] === null || $p['lng'] === null));
        usort($semCoordenadas, fn ($a, $b) => [$a['periodo'] === 'TARDE', $a['horario'] ?? '99'] <=> [$b['periodo'] === 'TARDE', $b['horario'] ?? '99']);

        $ordem = [];
        [$lat, $lng] = [$origemLat, $origemLng];
        foreach (['MANHA', 'TARDE'] as $periodo) {
            $restantes = array_values(array_filter($comCoordenadas, fn ($p) => $p['periodo'] === $periodo));
            $comHorario = array_values(array_filter($restantes, fn ($p) => $p['horario'] !== null));
            usort($comHorario, $porHorario);
            $semHorario = array_values(array_filter($restantes, fn ($p) => $p['horario'] === null));

            while ($comHorario || $semHorario) {
                $candidatas = $semHorario;
                if ($comHorario) {
                    $candidatas[] = $comHorario[0];
                }
                // Sem origem conhecida, começa pela primeira com horário ou pela primeira da lista.
                $proxima = $lat === null ? ($comHorario[0] ?? $semHorario[0])
                    : collect($candidatas)->sortBy(fn ($p) => [self::distanciaMetros($lat, $lng, $p['lat'], $p['lng']), $p['chave']])->first();

                $ordem[] = $proxima['chave'];
                [$lat, $lng] = [$proxima['lat'], $proxima['lng']];
                $comHorario = array_values(array_filter($comHorario, fn ($p) => $p['chave'] !== $proxima['chave']));
                $semHorario = array_values(array_filter($semHorario, fn ($p) => $p['chave'] !== $proxima['chave']));
            }
        }

        return [...$ordem, ...array_column($semCoordenadas, 'chave')];
    }

    /**
     * RN48: links do Google Maps com as paradas na ordem, em trechos de até nove paradas. Cada trecho começa onde o
     * anterior terminou; o primeiro começa no laboratório (ou na localização atual, sem origem).
     *
     * @param  list<array{0: float, 1: float}>  $paradas  [lat, lng] na ordem de visita
     * @return list<string>
     */
    public static function linksGoogleMaps(array $paradas, ?float $origemLat, ?float $origemLng): array
    {
        $links = [];
        $origem = $origemLat !== null && $origemLng !== null ? [$origemLat, $origemLng] : null;
        foreach (array_chunk($paradas, self::PARADAS_POR_TRECHO) as $trecho) {
            $destino = array_pop($trecho);
            $parametros = ['api' => 1];
            if ($origem) {
                $parametros['origin'] = self::coordenada($origem);
            }
            $parametros['destination'] = self::coordenada($destino);
            if ($trecho) {
                $parametros['waypoints'] = implode('|', array_map(self::coordenada(...), $trecho));
            }
            $parametros['travelmode'] = 'driving';
            $links[] = 'https://www.google.com/maps/dir/?'.http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
            $origem = $destino;
        }

        return $links;
    }

    /** RN48: o Waze aceita um destino por vez. */
    public static function linkWaze(float $lat, float $lng): string
    {
        return 'https://waze.com/ul?ll='.self::coordenada([$lat, $lng]).'&navigate=yes';
    }

    /** @param  array{0: float, 1: float}  $ponto */
    private static function coordenada(array $ponto): string
    {
        return rtrim(rtrim(number_format($ponto[0], 6, '.', ''), '0'), '.').','.rtrim(rtrim(number_format($ponto[1], 6, '.', ''), '0'), '.');
    }
}
