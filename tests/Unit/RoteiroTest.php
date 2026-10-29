<?php

namespace Tests\Unit;

use App\Support\Roteiro;
use PHPUnit\Framework\TestCase;

// MOD11 – ordem sugerida (RN47) e links de navegação (RN48)
class RoteiroTest extends TestCase
{
    private const LAB = [-22.9056, -47.0608];

    private function parada(string $chave, ?float $lat, ?float $lng, string $periodo = 'MANHA', ?string $horario = null): array
    {
        return ['chave' => $chave, 'periodo' => $periodo, 'horario' => $horario, 'lat' => $lat, 'lng' => $lng];
    }

    public function test_ct31_ordem_pelo_ponto_mais_proximo_a_partir_do_laboratorio_e_sem_coordenadas_no_fim(): void
    {
        // Longe (≈ 5,5 km), perto (≈ 1,1 km) e médio (≈ 1,1 km além do perto, na mesma direção).
        $paradas = [
            $this->parada('longe', -22.9556, -47.0608),
            $this->parada('sem', null, null),
            $this->parada('perto', -22.9156, -47.0608),
            $this->parada('medio', -22.9256, -47.0608),
        ];

        $this->assertSame(['perto', 'medio', 'longe', 'sem'], Roteiro::ordemSugerida($paradas, ...self::LAB));
    }

    public function test_rn47_manha_antes_da_tarde_e_horario_combinado_respeitado(): void
    {
        $paradas = [
            $this->parada('tarde-perto', -22.9066, -47.0608, 'TARDE'),
            $this->parada('manha-longe', -22.9556, -47.0608),
            $this->parada('manha-10h', -22.9956, -47.0608, 'MANHA', '10:00'),
            $this->parada('manha-8h', -22.9800, -47.0608, 'MANHA', '08:00'),
        ];

        $ordem = Roteiro::ordemSugerida($paradas, ...self::LAB);

        $this->assertSame('tarde-perto', $ordem[3]);
        $this->assertLessThan(array_search('manha-10h', $ordem), array_search('manha-8h', $ordem));
    }

    public function test_rn47_distancia_em_linha_reta(): void
    {
        // 0,01° de latitude ≈ 1,11 km.
        $this->assertEqualsWithDelta(1112, Roteiro::distanciaMetros(-22.90, -47.06, -22.91, -47.06), 2);
    }

    public function test_ct32_link_do_google_maps_com_origem_no_laboratorio_e_pontos_na_ordem(): void
    {
        $links = Roteiro::linksGoogleMaps([[-22.91, -47.06], [-22.92, -47.07], [-22.93, -47.08]], ...self::LAB);

        $this->assertCount(1, $links);
        parse_str(parse_url($links[0], PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://www.google.com/maps/dir/?api=1', $links[0]);
        $this->assertSame('-22.9056,-47.0608', $q['origin']);
        $this->assertSame('-22.91,-47.06|-22.92,-47.07', $q['waypoints']);
        $this->assertSame('-22.93,-47.08', $q['destination']);
    }

    public function test_rn48_mais_de_nove_paradas_dividem_o_link_em_trechos(): void
    {
        $paradas = array_map(fn ($i) => [-22.90 - $i / 100, -47.06], range(1, 12));

        $links = Roteiro::linksGoogleMaps($paradas, ...self::LAB);

        $this->assertCount(2, $links);
        parse_str(parse_url($links[1], PHP_URL_QUERY), $q);
        $this->assertSame('-22.99,-47.06', $q['origin']); // o segundo trecho começa onde o primeiro terminou
        $this->assertSame('-23.02,-47.06', $q['destination']);
        $this->assertSame('https://waze.com/ul?ll=-22.91,-47.06&navigate=yes', Roteiro::linkWaze(-22.91, -47.06));
    }
}
