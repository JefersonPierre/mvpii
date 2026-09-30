<?php

namespace App\Support;

// Números no formato brasileiro: leitura do que o usuário digita e exibição nas telas.
final class Numero
{
    /**
     * Converte "1.234,56", "R$ 150,00", "6,0" ou "0.5" para o formato do banco ("1234.56").
     * Devolve null se vazio e o próprio texto se não for número (a validação "numeric" acusa o erro).
     */
    public static function ler(mixed $valor): ?string
    {
        $texto = trim(str_replace(['R$', ' ', "\u{a0}"], '', (string) $valor));
        if ($texto === '') {
            return null;
        }
        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }

        return is_numeric($texto) ? $texto : (string) $valor;
    }

    /** Ex.: 6 → "6", 0.0005 → "0,0005", 1234.5 → "1.234,5". */
    public static function decimal(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        $numero = (float) $valor;
        $sinal = $numero < 0 ? '-' : '';
        $texto = rtrim(rtrim(number_format(abs($numero), 6, '.', ''), '0'), '.');
        [$inteiro, $fracao] = array_pad(explode('.', $texto), 2, null);

        return $sinal.number_format((int) $inteiro, 0, ',', '.').($fracao !== null ? ','.$fracao : '');
    }

    /** Ex.: 150 → "R$ 150,00". */
    public static function moeda(mixed $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    /** Valor para preencher um campo de formulário: "150,00", "6,5" ou vazio. */
    public static function campo(mixed $valor, bool $moeda = false): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return $moeda ? number_format((float) $valor, 2, ',', '.') : self::decimal($valor);
    }
}
