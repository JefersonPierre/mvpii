<?php

namespace App\Http\Requests;

use App\Models\Coleta;
use App\Rules\ColetorAtivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC14 – dados do agendamento (RF27, RN29) e do reagendamento (RF29, RN31). Saldo e pontos de coleta (RN27, RN28)
 * são conferidos no serviço, dentro da transação.
 */
class AgendamentoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Linhas de amostra sem ponto escolhido são ignoradas.
        if ($this->has('amostras')) {
            $this->merge(['amostras' => array_values(array_filter((array) $this->input('amostras'), fn ($a) => filled($a['ponto_coleta_id'] ?? null)))]);
        }
    }

    public function rules(): array
    {
        /** @var Coleta|null $coleta */
        $coleta = $this->route('coleta');
        $reagendamento = $coleta !== null;

        return [
            'data' => ['required', 'date', 'after_or_equal:today'],
            'periodo' => ['required', Rule::in(array_keys(Coleta::PERIODOS))],
            'horario' => ['nullable', 'date_format:H:i'],
            'coletor_id' => ['required', 'integer', new ColetorAtivo($coleta?->coletor_id)],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
            'confirmar_conflito' => ['nullable', 'boolean'],
            ...($reagendamento ? [
                'motivo' => ['required', 'string', 'max:255'],
            ] : [
                'amostras' => ['required', 'array', 'min:1', 'max:50'],
                'amostras.*.ponto_coleta_id' => ['required', 'integer'],
                'amostras.*.itens' => ['required', 'array', 'min:1'],
                'amostras.*.itens.*' => ['integer'],
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'data.required' => 'Informe a data da coleta.',
            'data.after_or_equal' => 'A data da coleta não pode ser anterior a hoje.',
            'periodo.required' => 'Escolha o período.',
            'horario.date_format' => 'Informe o horário no formato 09:00.',
            'coletor_id.required' => 'Escolha o coletor responsável.',
            'motivo.required' => 'Informe o motivo do reagendamento.',
            'amostras.required' => 'Adicione ao menos uma amostra.',
            'amostras.min' => 'Adicione ao menos uma amostra.',
            'amostras.*.itens.required' => 'Marque ao menos um item a analisar.',
            'amostras.*.itens.min' => 'Marque ao menos um item a analisar.',
        ];
    }
}
