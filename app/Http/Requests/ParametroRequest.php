<?php

namespace App\Http\Requests;

use App\Models\Parametro;
use App\Rules\NomeUnico;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// UC05 – parâmetro de análise (RF13, RN05: nome único, unidade e preço obrigatórios)
class ParametroRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'preco' => Numero::ler($this->input('preco')),
            'limite_quantificacao' => Numero::ler($this->input('limite_quantificacao')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120',
                new NomeUnico('parametros', $this->route('parametro')?->id, 'Já existe um parâmetro com este nome.')],
            'unidade' => ['required', 'string', 'max:30'],
            'metodo' => ['nullable', 'string', 'max:150'],
            'limite_quantificacao' => ['nullable', 'numeric', 'min:0'],
            'categoria' => ['required', Rule::in(array_keys(Parametro::CATEGORIAS))],
            'preco' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'unidade' => 'a unidade de medida',
            'metodo' => 'o método de análise',
            'limite_quantificacao' => 'o limite de quantificação',
            'categoria' => 'a categoria',
            'preco' => 'o preço',
        ];
    }
}
