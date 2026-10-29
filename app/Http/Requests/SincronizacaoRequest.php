<?php

namespace App\Http\Requests;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\FotoAmostra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// UC22 – registro de coleta enviado pelo celular (RF40, RF41, RN40, RN41, RN45). As regras da coleta são as do UC16.
class SincronizacaoRequest extends FormRequest
{
    /** Diferença tolerada entre o relógio do aparelho e o do servidor. */
    private const TOLERANCIA_RELOGIO_MINUTOS = 10;

    public function rules(): array
    {
        $motivos = array_keys(Coleta::MOTIVOS_NAO_COLETA);

        return [
            'uuid' => ['required', 'uuid'], // RN40
            'coleta_id' => ['required', 'integer'],
            'agendada_para' => ['nullable', 'date_format:Y-m-d'],
            'acao' => ['required', Rule::in([Coleta::REALIZADA, Coleta::NAO_REALIZADA])],
            'origem' => ['required', Rule::in(['CELULAR', 'CELULAR_SEM_CONEXAO'])], // RN41
            'data_hora_coleta' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(self::TOLERANCIA_RELOGIO_MINUTOS)->toIso8601String()],
            'condicoes_tempo' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], // RN44
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'motivo_nao_realizada' => ['nullable', 'required_if:acao,'.Coleta::NAO_REALIZADA, Rule::in($motivos)],
            'motivo_nao_realizada_detalhe' => ['nullable', 'string', 'max:255'],
            'amostras' => ['array', 'required_if:acao,'.Coleta::REALIZADA],
            'amostras.*.id' => ['required', 'integer'],
            'amostras.*.situacao' => ['required', Rule::in([Amostra::COLETADA, Amostra::NAO_COLETADA])],
            'amostras.*.temperatura' => ['nullable', 'numeric', 'between:-10,60'],
            'amostras.*.observacoes' => ['nullable', 'string', 'max:255'],
            'amostras.*.motivo' => ['nullable', 'required_if:amostras.*.situacao,'.Amostra::NAO_COLETADA, Rule::in($motivos)],
            'amostras.*.medicoes' => ['nullable', 'array'],
            'amostras.*.medicoes.*' => ['nullable', 'numeric'],
            'amostras.*.fotos' => ['nullable', 'array', 'max:'.FotoAmostra::MAXIMO_POR_AMOSTRA], // RN45
            'amostras.*.fotos.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_hora_coleta.before_or_equal' => 'A data e a hora da coleta não podem ser futuras. Confira o relógio do celular.',
            'motivo_nao_realizada.required_if' => 'Informe o motivo da coleta não realizada.',
            'amostras.*.motivo.required_if' => 'Informe o motivo da amostra não coletada.',
            'amostras.*.temperatura.between' => 'Temperatura fora da faixa esperada (−10 a 60 °C).',
            'amostras.*.medicoes.*.numeric' => 'Informe as medições de campo em números, ex.: 7,2.',
            'amostras.*.fotos.max' => 'Cada amostra aceita até três fotos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // O aparelho pode mandar vírgula decimal.
        $ler = fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v;
        $amostras = collect((array) $this->input('amostras', []))->map(fn ($a) => [
            ...(array) $a,
            'temperatura' => $ler($a['temperatura'] ?? null) ?: null,
            'medicoes' => collect((array) ($a['medicoes'] ?? []))->map(fn ($v) => $ler($v) === '' ? null : $ler($v))->all(),
        ])->all();
        $this->merge(['amostras' => $amostras]);
    }
}
