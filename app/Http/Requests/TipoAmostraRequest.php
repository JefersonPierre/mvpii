<?php

namespace App\Http\Requests;

use App\Models\TipoAmostra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// UC05 1a – tipo de amostra (RF12, RN05: nome único)
class TipoAmostraRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['descricao' => 'a descrição'];
    }

    /** RN05: o nome é único, sem diferenciar maiúsculas e minúsculas. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('nome')) {
                    return;
                }
                $repetido = TipoAmostra::whereRaw('LOWER(nome) = ?', [mb_strtolower($this->input('nome'))])
                    ->when($this->route('tipo'), fn ($q, TipoAmostra $atual) => $q->whereKeyNot($atual->id))
                    ->exists();
                if ($repetido) {
                    $validator->errors()->add('nome', 'Já existe um tipo de amostra com este nome.');
                }
            },
        ];
    }
}
