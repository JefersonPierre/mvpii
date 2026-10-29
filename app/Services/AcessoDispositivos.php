<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Models\Usuario;
use Illuminate\Support\Str;

// RN39 / RNF16: acesso do celular do coletor por token, criado quando ele entra no sistema com conexão.
class AcessoDispositivos
{
    /**
     * Cria o acesso do aparelho (ou renova, se o aparelho já tinha um). O token só é devolvido aqui: o servidor guarda o hash.
     *
     * @return array{token: string, expira_em: string}
     */
    public function conectar(Usuario $usuario, string $nome, ?string $tokenAnterior = null): array
    {
        if ($tokenAnterior && ($anterior = Dispositivo::porToken($tokenAnterior)) && $anterior->usuario_id === $usuario->id) {
            $anterior->forceFill(['revogado_em' => now()])->save();
        }

        $token = Str::random(64);
        $dispositivo = new Dispositivo;
        $dispositivo->forceFill([
            'usuario_id' => $usuario->id,
            'nome' => Str::limit($nome ?: 'Aparelho', 147),
            'token_hash' => hash('sha256', $token),
            'expira_em' => now()->addHours(Dispositivo::VALIDADE_HORAS),
        ])->save();

        return ['token' => $token, 'expira_em' => $dispositivo->expira_em->toIso8601String()];
    }

    /** RNF16: o Administrador revoga o acesso de um aparelho (perdido, trocado). */
    public function revogar(Dispositivo $dispositivo): void
    {
        if ($dispositivo->revogado_em === null) {
            $dispositivo->forceFill(['revogado_em' => now()])->save();
        }
    }

    /** Ao sair do sistema no aparelho, o acesso dele deixa de valer. */
    public function desconectar(?string $token): void
    {
        if ($token && $dispositivo = Dispositivo::porToken($token)) {
            $this->revogar($dispositivo);
        }
    }
}
