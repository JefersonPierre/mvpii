<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * RN04: registros inativos não podem ser usados em novas operações. Na edição, o registro pode continuar
 * com o valor que já tinha, mesmo que ele tenha sido inativado depois.
 */
class RegistroAtivo implements ValidationRule
{
    /** @param  list<int>  $permitidos  ids já vinculados ao registro em edição */
    public function __construct(
        private readonly string $tabela,
        private readonly string $mensagem,
        private readonly array $permitidos = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array((int) $value, $this->permitidos, true)) {
            return;
        }
        $ativo = DB::table($this->tabela)->where('id', (int) $value)->value('ativo');
        if (! $ativo) {
            $fail($this->mensagem);
        }
    }
}
