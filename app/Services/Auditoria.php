<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;

// RN08: toda inclusão, alteração e inativação registra usuário, data/hora e valores anterior e novo.
class Auditoria
{
    public const INCLUSAO = 'INCLUSAO';

    public const ALTERACAO = 'ALTERACAO';

    public const INATIVACAO = 'INATIVACAO';

    public const REATIVACAO = 'REATIVACAO';

    /** Quando verdadeiro, as alterações são registradas como feitas pelo sistema (sem usuário). */
    private bool $sistema = false;

    /**
     * Executa uma operação automática (ex.: expiração de orçamentos, RN17) registrando o "Sistema" como
     * responsável, e não o usuário que por acaso abriu a tela.
     *
     * @template T
     *
     * @param  Closure(): T  $operacao
     * @return T
     */
    public function comoSistema(Closure $operacao): mixed
    {
        $this->sistema = true;
        try {
            return $operacao();
        } finally {
            $this->sistema = false;
        }
    }

    /**
     * Grava uma linha por campo que mudou. Chame dentro da mesma transação da operação.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @param  list<string>  $campos
     */
    public function registrar(string $entidade, int $registroId, string $acao, array $antes, array $depois, array $campos): void
    {
        $agora = now();
        foreach (self::diferencas($antes, $depois, $campos) as $d) {
            RegistroAuditoria::create([
                'usuario_id' => $this->sistema ? null : Auth::id(),
                'entidade' => $entidade,
                'registro_id' => $registroId,
                'acao' => $acao,
                'data_hora' => $agora,
                ...$d,
            ]);
        }
    }

    /**
     * Compara os campos informados e devolve só os que mudaram.
     *
     * @return list<array{campo: string, valor_anterior: string|null, valor_novo: string|null}>
     */
    public static function diferencas(array $antes, array $depois, array $campos): array
    {
        $resultado = [];
        foreach ($campos as $campo) {
            $anterior = self::texto($antes[$campo] ?? null);
            $novo = self::texto($depois[$campo] ?? null);
            if ($anterior !== $novo) {
                $resultado[] = ['campo' => $campo, 'valor_anterior' => $anterior, 'valor_novo' => $novo];
            }
        }

        return $resultado;
    }

    private static function texto(mixed $valor): ?string
    {
        return match (true) {
            $valor === null, $valor === '' => null,
            is_bool($valor) => $valor ? 'true' : 'false',
            $valor instanceof DateTimeInterface => $valor->format(DATE_ATOM),
            default => (string) $valor,
        };
    }
}
