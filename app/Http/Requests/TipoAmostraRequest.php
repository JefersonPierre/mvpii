<?php

namespace App\Http\Requests;

use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;

// UC05 1a – tipo de amostra (RF12, RN05: nome único)
class TipoAmostraRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:80',
                new NomeUnico('tipos_amostra', $this->route('tipo')?->id, 'Já existe um tipo de amostra com este nome.')],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['descricao' => 'a descrição'];
    }
}
