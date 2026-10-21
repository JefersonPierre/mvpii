<?php

namespace App\Http\Requests;

use App\Support\Perfis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// RF03 – dados do cadastro de usuário (RN11: e-mail único) e RF25 – perfis (RN23: ao menos um)
class UsuarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($this->route('usuario'))],
            'perfis' => ['required', 'array', 'min:1'],
            'perfis.*' => ['string', Rule::in(array_keys(Perfis::NOMES))],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail pode ter no máximo 150 caracteres.',
            'email.unique' => 'E-mail já cadastrado.',
            'perfis.required' => 'Marque ao menos um perfil.',
            'perfis.min' => 'Marque ao menos um perfil.',
            'perfis.*.in' => 'Perfil inválido.',
        ];
    }
}
