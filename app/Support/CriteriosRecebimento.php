<?php

namespace App\Support;

use Carbon\CarbonInterface;

// RN35: compara o tempo desde a coleta e a temperatura no recebimento com os critérios configurados (RF35).
final class CriteriosRecebimento
{
    public static function minutosDesdeColeta(CarbonInterface $coletadaEm, CarbonInterface $recebidaEm): int
    {
        return max(0, (int) $coletadaEm->diffInMinutes($recebidaEm, false));
    }

    /** Ex.: 190 → "3 h 10 min", 45 → "45 min". */
    public static function duracao(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $horas > 0 ? sprintf('%d h %02d min', $horas, $resto) : "{$resto} min";
    }

    public static function foraDoPrazo(int $minutos, int $prazoHoras): bool
    {
        return $minutos > $prazoHoras * 60;
    }

    public static function foraDaTemperatura(float|string|null $temperatura, float|string $maxima): bool
    {
        return $temperatura !== null && $temperatura !== '' && (float) $temperatura > (float) $maxima;
    }

    /**
     * Alertas do recebimento; vazio se estiver dentro dos critérios.
     *
     * @return list<string>
     */
    public static function alertas(int $minutos, float|string|null $temperatura, int $prazoHoras, float|string $maxima): array
    {
        return array_values(array_filter([
            self::foraDoPrazo($minutos, $prazoHoras)
                ? 'Prazo excedido: '.self::duracao($minutos)." desde a coleta (máximo de {$prazoHoras} h)." : null,
            self::foraDaTemperatura($temperatura, $maxima)
                ? 'Temperatura acima do máximo configurado ('.Numero::decimal($maxima).' °C).' : null,
        ]));
    }
}
