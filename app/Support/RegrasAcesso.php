<?php

namespace App\Support;

use Carbon\CarbonInterface;

// Regras de negócio do acesso (RN09). Funções puras para facilitar os testes.
final class RegrasAcesso
{
    public const MAX_TENTATIVAS = 5;

    public const MINUTOS_BLOQUEIO = 15;

    public const MINUTOS_VALIDADE_LINK = 60;

    /** Link enviado ao usuário recém-cadastrado para criar a primeira senha. */
    public const MINUTOS_VALIDADE_CONVITE = 24 * 60;

    public const MENSAGEM_SENHA = 'A senha deve ter no mínimo 8 caracteres, com letras e números.';

    /** RN09: mínimo de 8 caracteres, com letras e números. */
    public static function senhaAtendeRegra(string $senha): bool
    {
        return mb_strlen($senha) >= 8 && preg_match('/[A-Za-z]/', $senha) && preg_match('/\d/', $senha);
    }

    public static function estaBloqueado(?CarbonInterface $bloqueadoAte, CarbonInterface $agora): bool
    {
        return $bloqueadoAte !== null && $bloqueadoAte->greaterThan($agora);
    }

    /**
     * Conta uma tentativa incorreta; na quinta seguida bloqueia por 15 minutos e zera o contador.
     *
     * @return array{tentativas_falhas: int, bloqueado_ate: CarbonInterface|null, bloqueou: bool}
     */
    public static function registrarFalha(int $tentativasAtuais, CarbonInterface $agora): array
    {
        $tentativas = $tentativasAtuais + 1;
        if ($tentativas >= self::MAX_TENTATIVAS) {
            return [
                'tentativas_falhas' => 0,
                'bloqueado_ate' => $agora->copy()->addMinutes(self::MINUTOS_BLOQUEIO),
                'bloqueou' => true,
            ];
        }

        return ['tentativas_falhas' => $tentativas, 'bloqueado_ate' => null, 'bloqueou' => false];
    }
}
