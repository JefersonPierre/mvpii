<?php

namespace App\Http\Requests;

use App\Models\Amostra;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// UC17 – dados do recebimento (RF34, RN35): aceite ou rejeição com motivo.
class RecebimentoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'temperatura_recebimento' => Numero::ler($this->input('temperatura_recebimento')),
            ...collect(Amostra::CONDICOES_FRASCO)->map(fn ($nome, $campo) => $this->boolean($campo))->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'amostras' => ['required', 'array', 'min:1'],
            'amostras.*' => ['integer', 'exists:amostras,id'],
            'recebida_em' => ['required', 'date', 'before_or_equal:now'],
            'temperatura_recebimento' => ['required', 'numeric', 'between:-10,60'],
            'decisao' => ['required', Rule::in(['ACEITAR', 'REJEITAR'])],
            'motivo_rejeicao' => ['nullable', 'required_if:decisao,REJEITAR', Rule::in(array_keys(Amostra::MOTIVOS_REJEICAO))],
            'observacoes_recebimento' => ['nullable', 'string', 'max:255'],
            ...collect(Amostra::CONDICOES_FRASCO)->map(fn () => ['boolean'])->all(),
        ];
    }

    /** UC17 1a: o recebimento em conjunto é de amostras da mesma coleta. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $ids = (array) $this->input('amostras', []);
            if (count($ids) > 1 && Amostra::whereIn('id', $ids)->distinct()->count('coleta_id') > 1) {
                $validator->errors()->add('amostras', 'Receba em conjunto somente amostras da mesma coleta.');
            }
            if ($this->input('decisao') === 'REJEITAR' && $this->input('motivo_rejeicao') === 'OUTRO' && blank($this->input('observacoes_recebimento'))) {
                $validator->errors()->add('observacoes_recebimento', 'Descreva o motivo da rejeição.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'amostras.required' => 'Selecione a amostra.',
            'recebida_em.required' => 'Informe a data e a hora do recebimento.',
            'recebida_em.before_or_equal' => 'A data e a hora do recebimento não podem ser futuras.',
            'temperatura_recebimento.required' => 'Informe a temperatura no recebimento.',
            'temperatura_recebimento.numeric' => 'Informe a temperatura em °C, ex.: 6,5.',
            'temperatura_recebimento.between' => 'Temperatura fora da faixa esperada (−10 a 60 °C).',
            'motivo_rejeicao.required_if' => 'Informe o motivo da rejeição.',
        ];
    }
}
