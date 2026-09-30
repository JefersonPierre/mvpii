<?php

namespace App\Http\Requests;

use App\Models\Legislacao;
use App\Models\Limite;
use App\Models\Parametro;
use App\Models\TipoAmostra;
use App\Rules\RegistroAtivo;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// UC06 – limite de um parâmetro (RF16, RN06)
class LimiteRequest extends FormRequest
{
    public function legislacao(): Legislacao
    {
        return $this->route('legislacao') ?? $this->route('limite')->legislacao;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'valor_minimo' => Numero::ler($this->input('valor_minimo')),
            'valor_maximo' => Numero::ler($this->input('valor_maximo')),
        ]);
    }

    public function rules(): array
    {
        /** @var Limite|null $limite */
        $limite = $this->route('limite');
        $tipos = $this->legislacao()->tiposAmostra()->pluck('tipos_amostra.id')->all();

        return [
            'parametro_id' => ['required', 'integer',
                new RegistroAtivo('parametros', 'Selecione um parâmetro ativo.', array_filter([$limite?->parametro_id]))],
            'tipo_amostra_id' => ['required', 'integer', Rule::in($tipos)],
            'tipo' => ['required', Rule::in(array_keys(Limite::TIPOS))],
            'valor_minimo' => ['nullable', 'required_if:tipo,MINIMO,FAIXA', 'numeric'],
            'valor_maximo' => ['nullable', 'required_if:tipo,MAXIMO,FAIXA', 'numeric'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'parametro_id.required' => 'Selecione o parâmetro.',
            'tipo_amostra_id.required' => 'Selecione o tipo de amostra.',
            'tipo_amostra_id.in' => 'O tipo de amostra não é abrangido por esta legislação.',
            'tipo.required' => 'Selecione o tipo de limite.',
            'valor_minimo.required_if' => 'Informe o valor mínimo.',
            'valor_maximo.required_if' => 'Informe o valor máximo.',
        ];
    }

    public function attributes(): array
    {
        return ['valor_minimo' => 'o valor mínimo', 'valor_maximo' => 'o valor máximo', 'observacao' => 'a observação'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            // RN06: em limites do tipo faixa, o mínimo deve ser menor que o máximo.
            if ($this->input('tipo') === 'FAIXA' && (float) $this->input('valor_minimo') >= (float) $this->input('valor_maximo')) {
                $validator->errors()->add('valor_minimo', 'O valor mínimo deve ser menor que o máximo.');
            }
            // RN06: no máximo um limite por parâmetro, legislação e tipo de amostra.
            $repetido = $this->legislacao()->limites()
                ->where(['parametro_id' => $this->input('parametro_id'), 'tipo_amostra_id' => $this->input('tipo_amostra_id')])
                ->when($this->route('limite'), fn ($q, Limite $atual) => $q->whereKeyNot($atual->id))
                ->exists();
            if ($repetido) {
                $parametro = Parametro::find($this->input('parametro_id'))->nome;
                $tipo = mb_strtolower(TipoAmostra::find($this->input('tipo_amostra_id'))->nome);
                $validator->errors()->add('parametro_id', "Já existe limite de {$parametro} para {$tipo} nesta legislação.");
            }
        }];
    }

    /** Guarda só os valores que o tipo de limite usa (ex.: "máximo" não tem mínimo). */
    public function dadosDoLimite(): array
    {
        $dados = $this->validated();
        $tipo = $dados['tipo'];

        return [
            ...$dados,
            'valor_minimo' => in_array($tipo, ['MINIMO', 'FAIXA'], true) ? $dados['valor_minimo'] : null,
            'valor_maximo' => in_array($tipo, ['MAXIMO', 'FAIXA'], true) ? $dados['valor_maximo'] : null,
        ];
    }
}
