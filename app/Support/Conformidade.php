<?php

namespace App\Support;

// RN52: compara um resultado com o limite da legislação. A comparação é do sistema e não pode ser alterada pelo usuário.
final class Conformidade
{
    public const CONFORME = 'CONFORME';

    public const NAO_CONFORME = 'NAO_CONFORME';

    public const INCONCLUSIVO = 'INCONCLUSIVO';

    public const SEM_LIMITE = 'SEM_LIMITE';

    /**
     * @param  string|null  $tipo  tipo do limite (MAXIMO, MINIMO, FAIXA ou AUSENCIA); null = parâmetro sem limite
     * @param  float|string|null  $valor  valor medido, na unidade do parâmetro
     * @param  bool  $menorQueLq  resultado "< LQ"
     * @param  string|null  $presenca  PRESENTE ou AUSENTE
     * @param  float|string|null  $lq  limite de quantificação do parâmetro
     * @return string|null null enquanto não há resultado
     */
    public static function avaliar(?string $tipo, float|string|null $minimo, float|string|null $maximo,
        float|string|null $valor, bool $menorQueLq = false, ?string $presenca = null, float|string|null $lq = null): ?string
    {
        $temResultado = $menorQueLq || $presenca !== null || ($valor !== null && $valor !== '');
        if (! $temResultado) {
            return null;
        }
        if ($tipo === null) {
            return self::SEM_LIMITE;
        }

        if ($tipo === 'AUSENCIA') {
            return match ($presenca) {
                'AUSENTE' => self::CONFORME,
                'PRESENTE' => self::NAO_CONFORME,
                default => self::INCONCLUSIVO,
            };
        }

        // "< LQ": conforme no limite Máximo, salvo se o LQ passar do máximo (inconclusivo). Nos limites Mínimo e
        // Faixa, um valor abaixo do LQ só é conforme se o LQ ficar abaixo do mínimo exigido.
        if ($menorQueLq) {
            return match ($tipo) {
                'MAXIMO' => $lq !== null && (float) $lq > (float) $maximo ? self::INCONCLUSIVO : self::CONFORME,
                default => $lq !== null && (float) $lq <= (float) $minimo ? self::NAO_CONFORME : self::INCONCLUSIVO,
            };
        }

        $v = (float) $valor;
        $conforme = match ($tipo) {
            'MAXIMO' => $v <= (float) $maximo,
            'MINIMO' => $v >= (float) $minimo,
            'FAIXA' => $v >= (float) $minimo && $v <= (float) $maximo,
            default => null,
        };

        return match ($conforme) {
            true => self::CONFORME,
            false => self::NAO_CONFORME,
            null => self::INCONCLUSIVO,
        };
    }

    /**
     * RN52: a amostra é Não conforme se ao menos um parâmetro for Não conforme. Sem legislação, fica sem referência.
     *
     * @param  iterable<string|null>  $conformidades  uma por parâmetro
     */
    public static function conclusao(iterable $conformidades, bool $temLegislacao): string
    {
        if (! $temLegislacao) {
            return 'SEM_LEGISLACAO';
        }
        $lista = collect($conformidades);

        return match (true) {
            $lista->contains(self::NAO_CONFORME) => 'NAO_CONFORME',
            $lista->contains(self::INCONCLUSIVO) => 'INCONCLUSIVA',
            default => 'CONFORME',
        };
    }
}
