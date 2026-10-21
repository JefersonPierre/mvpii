<?php

namespace App\Http\Requests;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// UC16 – dados do registro da coleta (RF33, RN32): data e hora não futuras; motivo da amostra não coletada.
class RegistroColetaRequest extends FormRequest
{
    public function naoRealizada(): bool
    {
        return $this->input('acao') === 'nao_realizada';
    }

    protected function prepareForValidation(): void
    {
        $amostras = collect((array) $this->input('amostras', []))
            ->map(fn ($a) => [...(array) $a, 'temperatura' => Numero::ler($a['temperatura'] ?? null)])
            ->all();
        $this->merge(['amostras' => $amostras]);
    }

    public function rules(): array
    {
        /** @var Coleta $coleta */
        $coleta = $this->route('coleta');
        $motivos = array_keys(Coleta::MOTIVOS_NAO_COLETA);
        $comum = [
            'data_hora_coleta' => ['required', 'date', 'before_or_equal:now'],
            'condicoes_tempo' => ['nullable', 'string', 'max:150'],
        ];

        if ($this->naoRealizada()) {
            return [...$comum,
                'motivo_nao_realizada' => ['required', Rule::in($motivos)],
                'motivo_nao_realizada_detalhe' => ['nullable', 'required_if:motivo_nao_realizada,OUTRO', 'string', 'max:255'],
            ];
        }

        $regras = $comum;
        foreach ($coleta->amostras()->where('situacao', Amostra::PREVISTA)->pluck('id') as $id) {
            $regras["amostras.$id.situacao"] = ['required', Rule::in([Amostra::COLETADA, Amostra::NAO_COLETADA])];
            $regras["amostras.$id.temperatura"] = ['nullable', 'numeric', 'between:-10,60'];
            $regras["amostras.$id.observacoes"] = ['nullable', 'string', 'max:255', "required_if:amostras.$id.motivo,OUTRO"];
            $regras["amostras.$id.motivo"] = ["required_if:amostras.$id.situacao,".Amostra::NAO_COLETADA, 'nullable', Rule::in($motivos)];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'data_hora_coleta.required' => 'Informe a data e a hora da coleta.',
            'data_hora_coleta.before_or_equal' => 'A data e a hora da coleta não podem ser futuras.',
            'motivo_nao_realizada.required' => 'Informe o motivo da coleta não realizada.',
            'motivo_nao_realizada_detalhe.required_if' => 'Descreva o motivo.',
            'amostras.*.situacao.required' => 'Informe se a amostra foi coletada.',
            'amostras.*.motivo.required_if' => 'Informe o motivo da amostra não coletada.',
            'amostras.*.observacoes.required_if' => 'Descreva o motivo.',
            'amostras.*.temperatura.numeric' => 'Informe a temperatura em °C, ex.: 6,5.',
            'amostras.*.temperatura.between' => 'Temperatura fora da faixa esperada (−10 a 60 °C).',
        ];
    }
}
