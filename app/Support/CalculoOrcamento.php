<?php

namespace App\Support;

/**
 * RN13: valor total = soma de (preço unitário × quantidade) dos itens + taxa de coleta − desconto.
 * O desconto é um percentual sobre itens + taxa (CT08: 380 + 50 = 430; 10% = 43; total 387).
 * As contas são feitas em centavos para evitar erros de arredondamento.
 */
final class CalculoOrcamento
{
    /**
     * @param  list<array{preco: string|float, quantidade: int}>  $itens
     * @return array{subtotais: list<string>, subtotal_itens: string, valor_desconto: string, valor_total: string}
     */
    public static function calcular(array $itens, string|float $taxaColeta, string|float $descontoPercentual): array
    {
        $subtotais = array_map(fn ($item) => self::centavos($item['preco']) * (int) $item['quantidade'], $itens);
        $somaItens = array_sum($subtotais);
        $base = $somaItens + self::centavos($taxaColeta);
        $desconto = (int) round($base * (float) $descontoPercentual / 100, 0, PHP_ROUND_HALF_UP);

        return [
            'subtotais' => array_map(self::reais(...), $subtotais),
            'subtotal_itens' => self::reais($somaItens),
            'valor_desconto' => self::reais($desconto),
            'valor_total' => self::reais($base - $desconto),
        ];
    }

    private static function centavos(string|float $valor): int
    {
        return (int) round((float) $valor * 100);
    }

    private static function reais(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
