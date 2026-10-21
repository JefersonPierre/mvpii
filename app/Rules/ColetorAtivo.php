<?php

namespace App\Rules;

use App\Models\Usuario;
use App\Support\Perfis;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** RN29: o coletor responsável deve estar ativo e ter o perfil Coletor. No reagendamento, o atual pode continuar. */
class ColetorAtivo implements ValidationRule
{
    public function __construct(private readonly ?int $atual = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->atual !== null && (int) $value === $this->atual) {
            return;
        }
        if (! Usuario::ativosComPerfil(Perfis::COLETOR)->whereKey((int) $value)->exists()) {
            $fail('Selecione um coletor ativo com o perfil Coletor.');
        }
    }
}
