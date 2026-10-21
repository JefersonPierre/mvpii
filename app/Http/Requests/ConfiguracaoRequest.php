<?php

namespace App\Http\Requests;

use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;

// UC12 – configurações comerciais (RF22, RN14) e UC19 – critérios de recebimento (RF35, RN35)
class ConfiguracaoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'taxa_coleta' => Numero::ler($this->input('taxa_coleta')),
            'desconto_maximo' => Numero::ler($this->input('desconto_maximo')),
            'temperatura_maxima_recebimento' => Numero::ler($this->input('temperatura_maxima_recebimento')),
        ]);
    }

    public function rules(): array
    {
        return [
            'validade_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'taxa_coleta' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'desconto_maximo' => ['required', 'numeric', 'min:0', 'max:100'],
            'condicoes_comerciais' => ['nullable', 'string', 'max:5000'],
            'prazo_recebimento_horas' => ['required', 'integer', 'min:1', 'max:720'],
            'temperatura_maxima_recebimento' => ['required', 'numeric', 'gt:0', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'validade_dias.min' => 'A validade deve ser de pelo menos 1 dia.',
            'desconto_maximo.max' => 'O desconto máximo não pode passar de 100%.',
            'prazo_recebimento_horas.min' => 'O prazo deve ser de pelo menos 1 hora.',
            'temperatura_maxima_recebimento.gt' => 'A temperatura máxima deve ser maior que zero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'validade_dias' => 'a validade padrão',
            'taxa_coleta' => 'a taxa de coleta padrão',
            'desconto_maximo' => 'o desconto máximo',
            'condicoes_comerciais' => 'as condições comerciais',
            'prazo_recebimento_horas' => 'o prazo máximo de recebimento',
            'temperatura_maxima_recebimento' => 'a temperatura máxima de recebimento',
        ];
    }
}
