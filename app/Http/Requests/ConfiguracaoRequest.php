<?php

namespace App\Http\Requests;

use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;

// UC12 – configurações comerciais (RF22, RN14)
class ConfiguracaoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'taxa_coleta' => Numero::ler($this->input('taxa_coleta')),
            'desconto_maximo' => Numero::ler($this->input('desconto_maximo')),
        ]);
    }

    public function rules(): array
    {
        return [
            'validade_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'taxa_coleta' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'desconto_maximo' => ['required', 'numeric', 'min:0', 'max:100'],
            'condicoes_comerciais' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'validade_dias.min' => 'A validade deve ser de pelo menos 1 dia.',
            'desconto_maximo.max' => 'O desconto máximo não pode passar de 100%.',
        ];
    }

    public function attributes(): array
    {
        return [
            'validade_dias' => 'a validade padrão',
            'taxa_coleta' => 'a taxa de coleta padrão',
            'desconto_maximo' => 'o desconto máximo',
            'condicoes_comerciais' => 'as condições comerciais',
        ];
    }
}
