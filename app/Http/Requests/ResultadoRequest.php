<?php

namespace App\Http\Requests;

use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// UC24 – resultados da amostra (RF48, RN50): valor na unidade do parâmetro, "< LQ" ou Presente/Ausente.
class ResultadoRequest extends FormRequest
{
    public function enviarParaRevisao(): bool
    {
        return $this->input('acao') === 'enviar';
    }

    protected function prepareForValidation(): void
    {
        $resultados = collect((array) $this->input('resultados', []))
            ->map(fn ($r) => [...(array) $r, 'valor' => Numero::ler($r['valor'] ?? null), 'menor_que_lq' => ! empty($r['menor_que_lq'])])
            ->all();
        $this->merge(['resultados' => $resultados]);
    }

    public function rules(): array
    {
        return [
            'legislacao_id' => ['nullable', 'integer'],
            'resultados' => ['array'],
            'resultados.*.valor' => ['nullable', 'numeric', 'min:-1000000', 'max:100000000'],
            'resultados.*.menor_que_lq' => ['boolean'],
            'resultados.*.presenca' => ['nullable', Rule::in(['PRESENTE', 'AUSENTE'])],
            'resultados.*.data_analise' => ['nullable', 'date', 'before_or_equal:today'],
            'resultados.*.observacoes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'resultados.*.valor.numeric' => 'Informe o resultado em número, ex.: 0,8.',
            'resultados.*.data_analise.before_or_equal' => 'A data da análise não pode ser futura.',
        ];
    }
}
