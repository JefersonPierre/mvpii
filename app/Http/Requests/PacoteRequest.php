<?php

namespace App\Http\Requests;

use App\Models\Pacote;
use App\Rules\NomeUnico;
use App\Rules\RegistroAtivo;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;

// UC05 1b – pacote de análise (RF14, RN05: nome único e preço obrigatório)
class PacoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['preco' => Numero::ler($this->input('preco'))]);
    }

    public function rules(): array
    {
        /** @var Pacote|null $pacote */
        $pacote = $this->route('pacote');
        $parametrosAtuais = $pacote?->parametros()->pluck('parametros.id')->all() ?? [];

        return [
            'nome' => ['required', 'string', 'max:120',
                new NomeUnico('pacotes', $pacote?->id, 'Já existe um pacote com este nome.')],
            'tipo_amostra_id' => ['required', 'integer',
                new RegistroAtivo('tipos_amostra', 'Selecione um tipo de amostra ativo.', array_filter([$pacote?->tipo_amostra_id]))],
            'parametros' => ['required', 'array', 'min:1'],
            'parametros.*' => ['integer', new RegistroAtivo('parametros', 'O pacote só pode incluir parâmetros ativos.', $parametrosAtuais)],
            'preco' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_amostra_id.required' => 'Selecione o tipo de amostra.',
            'parametros.required' => 'Selecione os parâmetros do pacote.',
        ];
    }

    public function attributes(): array
    {
        return ['preco' => 'o preço do pacote'];
    }
}
