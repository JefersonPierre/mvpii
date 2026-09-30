<?php

namespace App\Http\Requests;

use App\Models\Legislacao;
use App\Models\TipoAmostra;
use App\Rules\NomeUnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// UC06 – legislação de referência (RF15, RN07)
class LegislacaoRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Legislacao|null $legislacao */
        $legislacao = $this->route('legislacao');

        $regras = [
            'nome' => ['required', 'string', 'max:150',
                new NomeUnico('legislacoes', $legislacao?->id, 'Já existe uma legislação com este nome.')],
            'orgao_emissor' => ['required', 'string', 'max:120'],
            'inicio_vigencia' => ['required', 'date'],
            'fim_vigencia' => ['nullable', 'date', 'after_or_equal:inicio_vigencia'],
            'tipos_amostra' => ['required', 'array', 'min:1'],
            'tipos_amostra.*' => ['integer', 'exists:tipos_amostra,id'],
        ];

        // UC06 1a: substituir uma legislação (só no cadastro de uma nova).
        if (! $legislacao) {
            $regras += [
                'substitui_id' => ['nullable', 'integer', 'exists:legislacoes,id'],
                'fim_anterior' => ['nullable', 'required_with:substitui_id', 'date', 'before:inicio_vigencia'],
                'copiar_limites' => ['boolean'],
            ];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'tipos_amostra.required' => 'Marque os tipos de amostra abrangidos.',
            'fim_vigencia.after_or_equal' => 'O fim da vigência não pode ser anterior ao início.',
            'fim_anterior.required_with' => 'Informe a data de fim da legislação substituída.',
            'fim_anterior.before' => 'A legislação substituída deve terminar antes do início da nova.',
        ];
    }

    public function attributes(): array
    {
        return [
            'orgao_emissor' => 'o órgão emissor',
            'inicio_vigencia' => 'o início da vigência',
            'fim_vigencia' => 'o fim da vigência',
            'fim_anterior' => 'o fim da legislação substituída',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $this->validarSubstituida($validator);
            $this->validarTiposComLimites($validator);
        }];
    }

    /** A legislação substituída precisa estar em vigor e terminar depois de começar. */
    private function validarSubstituida(Validator $validator): void
    {
        $anterior = $this->filled('substitui_id') ? Legislacao::find($this->input('substitui_id')) : null;
        if (! $anterior) {
            return;
        }
        if ($anterior->substituida_por_id) {
            $validator->errors()->add('substitui_id', 'Esta legislação já foi substituída.');
        } elseif ($anterior->inicio_vigencia->gt($this->date('fim_anterior'))) {
            $validator->errors()->add('fim_anterior', 'O fim não pode ser anterior ao início da vigência da legislação substituída.');
        }
    }

    /** Não dá para tirar um tipo de amostra que ainda tem limites cadastrados nesta legislação. */
    private function validarTiposComLimites(Validator $validator): void
    {
        /** @var Legislacao|null $legislacao */
        $legislacao = $this->route('legislacao');
        if (! $legislacao) {
            return;
        }
        $removidos = $legislacao->limites()
            ->whereNotIn('tipo_amostra_id', array_map('intval', $this->input('tipos_amostra')))
            ->distinct()->pluck('tipo_amostra_id');
        if ($removidos->isNotEmpty()) {
            $nomes = TipoAmostra::whereIn('id', $removidos)->pluck('nome')->implode(', ');
            $validator->errors()->add('tipos_amostra', "Há limites cadastrados para {$nomes}. Remova esses limites antes de desmarcar o tipo.");
        }
    }
}
