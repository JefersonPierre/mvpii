<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/** RN05: nome único na tabela, sem diferenciar maiúsculas e minúsculas ("pH" = "PH"). */
class NomeUnico implements ValidationRule
{
    public function __construct(
        private readonly string $tabela,
        private readonly ?int $ignorarId,
        private readonly string $mensagem,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existe = DB::table($this->tabela)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim((string) $value))])
            ->when($this->ignorarId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($existe) {
            $fail($this->mensagem);
        }
    }
}
