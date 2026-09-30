<?php

namespace App\Support;

// RN01: validação e formatação de CPF e CNPJ. Funções puras para facilitar os testes.
final class Documento
{
    /** Remove máscara e espaços. O CNPJ pode ter letras (formato alfanumérico, vigente desde jul/2026). */
    public static function limpar(?string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor));
    }

    public static function valido(string $tipoPessoa, ?string $documento): bool
    {
        $doc = self::limpar($documento);

        return $tipoPessoa === 'F' ? self::cpfValido($doc) : self::cnpjValido($doc);
    }

    public static function cpfValido(string $cpf): bool
    {
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        foreach ([9, 10] as $tamanho) {
            $soma = 0;
            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (int) $cpf[$i] * ($tamanho + 1 - $i);
            }
            $digito = ($soma * 10) % 11 % 10;
            if ($digito !== (int) $cpf[$tamanho]) {
                return false;
            }
        }

        return true;
    }

    /**
     * CNPJ numérico ou alfanumérico: 12 caracteres (dígitos ou letras) + 2 dígitos verificadores.
     * Cada caractere vale o seu código ASCII menos 48 (assim os dígitos continuam valendo 0–9).
     */
    public static function cnpjValido(string $cnpj): bool
    {
        if (! preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        foreach ([12, 13] as $tamanho) {
            $soma = 0;
            $peso = $tamanho - 7;
            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (ord($cnpj[$i]) - 48) * $peso;
                $peso = $peso === 2 ? 9 : $peso - 1;
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ($digito !== (int) $cnpj[$tamanho]) {
                return false;
            }
        }

        return true;
    }

    public static function formatar(?string $documento): string
    {
        $d = self::limpar($documento);

        return match (strlen($d)) {
            11 => sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9)),
            14 => sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12)),
            default => $d,
        };
    }

    public static function formatarTelefone(?string $telefone): string
    {
        $t = preg_replace('/\D/', '', (string) $telefone);

        return match (strlen($t)) {
            10 => sprintf('(%s) %s-%s', substr($t, 0, 2), substr($t, 2, 4), substr($t, 6)),
            11 => sprintf('(%s) %s-%s', substr($t, 0, 2), substr($t, 2, 5), substr($t, 7)),
            default => $t,
        };
    }

    public static function formatarCep(?string $cep): string
    {
        $c = preg_replace('/\D/', '', (string) $cep);

        return strlen($c) === 8 ? substr($c, 0, 5).'-'.substr($c, 5) : $c;
    }
}
