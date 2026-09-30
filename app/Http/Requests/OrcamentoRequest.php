<?php

namespace App\Http\Requests;

use App\Models\Configuracao;
use App\Models\Contato;
use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Rules\RegistroAtivo;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// UC08 – dados do orçamento (RF17, RN04, RN14)
class OrcamentoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'taxa_coleta' => Numero::ler($this->input('taxa_coleta')),
            'desconto_percentual' => Numero::ler($this->input('desconto_percentual')) ?? '0',
            'itens' => array_values(array_filter((array) $this->input('itens', []), fn ($i) => filled($i['referencia'] ?? null))),
        ]);
    }

    public function rules(): array
    {
        /** @var Orcamento|null $orcamento */
        $orcamento = $this->route('orcamento');

        return [
            'cliente_id' => ['required', 'integer',
                new RegistroAtivo('clientes', 'Cliente inativo não pode receber orçamento.', array_filter([$orcamento?->cliente_id]))],
            'contato_id' => ['nullable', 'integer'],
            'tipo_amostra_id' => ['required', 'integer',
                new RegistroAtivo('tipos_amostra', 'Selecione um tipo de amostra ativo.', array_filter([$orcamento?->tipo_amostra_id]))],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id' => ['nullable', 'integer'],
            'itens.*.referencia' => ['required', 'regex:/^(PACOTE|PARAMETRO):\d+$/'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'taxa_coleta' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'desconto_percentual' => ['required', 'numeric', 'min:0', 'max:100'],
            'validade_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'condicoes_pagamento' => ['nullable', 'string', 'max:5000'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecione o cliente.',
            'tipo_amostra_id.required' => 'Selecione o tipo de amostra.',
            'itens.required' => 'Adicione ao menos um pacote ou parâmetro.',
            'itens.*.quantidade.min' => 'A quantidade de amostras deve ser pelo menos 1.',
            'itens.*.quantidade.required' => 'Informe a quantidade de amostras.',
        ];
    }

    public function attributes(): array
    {
        return [
            'taxa_coleta' => 'a taxa de coleta',
            'desconto_percentual' => 'o desconto',
            'validade_dias' => 'a validade',
            'condicoes_pagamento' => 'as condições de pagamento',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            // RN14: o desconto não pode ultrapassar o máximo das configurações.
            $maximo = (float) Configuracao::atual()->desconto_maximo;
            if ((float) $this->input('desconto_percentual') > $maximo) {
                $validator->errors()->add('desconto_percentual', 'Máximo permitido: '.Numero::decimal($maximo).'%.');
            }
            $this->validarContato($validator);
            $this->validarItens($validator);
        }];
    }

    private function validarContato(Validator $validator): void
    {
        if ($this->filled('contato_id')
            && ! Contato::whereKey($this->input('contato_id'))->where('cliente_id', $this->input('cliente_id'))->exists()) {
            $validator->errors()->add('contato_id', 'O contato não pertence ao cliente selecionado.');
        }
    }

    /**
     * Cada item existe no catálogo e está ativo (ou já estava neste orçamento), o pacote é do tipo de amostra
     * escolhido e nenhum item se repete.
     */
    private function validarItens(Validator $validator): void
    {
        /** @var Orcamento|null $orcamento */
        $orcamento = $this->route('orcamento');
        $jaNoOrcamento = $orcamento?->itens->map->referencia()->all() ?? [];
        $vistos = [];

        foreach ($this->input('itens') as $i => $item) {
            $referencia = $item['referencia'];
            [$tipo, $id] = explode(':', $referencia);
            $catalogo = $tipo === 'PACOTE' ? Pacote::find($id) : Parametro::find($id);

            if (in_array($referencia, $vistos, true)) {
                $validator->errors()->add("itens.$i.referencia", 'Item repetido: ajuste a quantidade na linha já existente.');
            } elseif (! $catalogo || (! $catalogo->ativo && ! in_array($referencia, $jaNoOrcamento, true))) {
                $validator->errors()->add("itens.$i.referencia", 'Item inativo ou inexistente no catálogo.');
            } elseif ($catalogo instanceof Pacote && $catalogo->tipo_amostra_id !== (int) $this->input('tipo_amostra_id')) {
                $validator->errors()->add("itens.$i.referencia", "O pacote {$catalogo->nome} é de outro tipo de amostra.");
            }
            $vistos[] = $referencia;
        }
    }
}
