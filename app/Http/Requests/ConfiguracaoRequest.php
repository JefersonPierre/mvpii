<?php

namespace App\Http\Requests;

use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;

// UC12 – configurações comerciais (RF22, RN14), UC19 – critérios de recebimento (RF35, RN35) e UC29 – laboratório (RF56)
class ConfiguracaoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'taxa_coleta' => Numero::ler($this->input('taxa_coleta')),
            'desconto_maximo' => Numero::ler($this->input('desconto_maximo')),
            'temperatura_maxima_recebimento' => Numero::ler($this->input('temperatura_maxima_recebimento')),
            'laboratorio_latitude' => str_replace(',', '.', trim((string) $this->input('laboratorio_latitude'))) ?: null,
            'laboratorio_longitude' => str_replace(',', '.', trim((string) $this->input('laboratorio_longitude'))) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'validade_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'taxa_coleta' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'desconto_maximo' => ['required', 'numeric', 'min:0', 'max:100'],
            'condicoes_comerciais' => ['nullable', 'string', 'max:5000'],
            'prazo_recebimento_horas' => ['required', 'integer', 'min:1', 'max:720'],
            'temperatura_maxima_recebimento' => ['required', 'numeric', 'gt:0', 'max:50'],
            // RF56: dados do laboratório no laudo e ponto de partida das rotas
            'laboratorio_nome' => ['nullable', 'string', 'max:150'],
            'laboratorio_endereco' => ['nullable', 'string', 'max:255'],
            'laboratorio_telefone' => ['nullable', 'string', 'max:30'],
            'laboratorio_latitude' => ['nullable', 'required_with:laboratorio_longitude', 'numeric', 'between:-90,90'],
            'laboratorio_longitude' => ['nullable', 'required_with:laboratorio_latitude', 'numeric', 'between:-180,180'],
            'responsavel_tecnico' => ['nullable', 'required_with:registro_profissional', 'string', 'max:150'],
            'registro_profissional' => ['nullable', 'required_with:responsavel_tecnico', 'string', 'max:60'],
            'observacoes_laudo' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'validade_dias.min' => 'A validade deve ser de pelo menos 1 dia.',
            'desconto_maximo.max' => 'O desconto máximo não pode passar de 100%.',
            'prazo_recebimento_horas.min' => 'O prazo deve ser de pelo menos 1 hora.',
            'temperatura_maxima_recebimento.gt' => 'A temperatura máxima deve ser maior que zero.',
            'responsavel_tecnico.required_with' => 'Informe o nome do responsável técnico.',
            'registro_profissional.required_with' => 'Informe o registro profissional do responsável técnico (ex.: CRQ IV 04123456).',
            'laboratorio_latitude.required_with' => 'Informe a latitude junto com a longitude.',
            'laboratorio_longitude.required_with' => 'Informe a longitude junto com a latitude.',
        ];
    }

    public function attributes(): array
    {
        return [
            'validade_dias' => 'a validade padrão',
            'taxa_coleta' => 'a taxa de coleta padrão',
            'desconto_maximo' => 'o desconto máximo',
            'condicoes_comerciais' => 'as condições comerciais',
            'prazo_recebimento_horas' => 'o prazo máximo de recebimento',
            'temperatura_maxima_recebimento' => 'a temperatura máxima de recebimento',
            'laboratorio_nome' => 'o nome do laboratório',
            'laboratorio_endereco' => 'o endereço do laboratório',
            'laboratorio_telefone' => 'o telefone do laboratório',
            'laboratorio_latitude' => 'a latitude',
            'laboratorio_longitude' => 'a longitude',
            'responsavel_tecnico' => 'o responsável técnico',
            'registro_profissional' => 'o registro profissional',
            'observacoes_laudo' => 'as observações do laudo',
        ];
    }
}
