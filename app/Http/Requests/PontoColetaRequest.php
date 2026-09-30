<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\PontoColeta;
use App\Models\TipoAmostra;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// UC04 – dados do ponto de coleta (RF09, RN03)
class PontoColetaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Coordenadas aceitam vírgula decimal (ex.: -22,9056).
        $decimal = fn (string $campo) => str_replace(',', '.', trim((string) $this->input($campo))) ?: null;

        $this->merge([
            'cep' => preg_replace('/\D/', '', (string) $this->input('cep')) ?: null,
            'uf' => strtoupper((string) $this->input('uf')) ?: null,
            'latitude' => $decimal('latitude'),
            'longitude' => $decimal('longitude'),
        ]);
    }

    public function rules(): array
    {
        /** @var PontoColeta|null $ponto */
        $ponto = $this->route('ponto');

        return [
            'identificacao' => ['required', 'string', 'max:150'],
            // RN04: tipo inativo não entra em novos cadastros, mas o ponto pode manter o que já tinha.
            'tipo_amostra_id' => ['required', 'integer', function (string $atributo, mixed $valor, Closure $falhar) use ($ponto) {
                $tipo = TipoAmostra::find($valor);
                if (! $tipo || (! $tipo->ativo && $ponto?->tipo_amostra_id !== $tipo->id)) {
                    $falhar('Selecione um tipo de amostra ativo.');
                }
            }],
            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['required', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => ['nullable', 'string', 'max:80'],
            'cidade' => ['required', 'string', 'max:80'],
            'uf' => ['required', Rule::in(Cliente::UFS)],
            'referencia' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_amostra_id.required' => 'Selecione o tipo de amostra padrão.',
            'cep.digits' => 'O CEP deve ter 8 dígitos.',
            'latitude.required_with' => 'Informe a latitude junto com a longitude.',
            'longitude.required_with' => 'Informe a longitude junto com a latitude.',
            'latitude.between' => 'A latitude deve estar entre -90 e 90.',
            'longitude.between' => 'A longitude deve estar entre -180 e 180.',
        ];
    }

    public function attributes(): array
    {
        return ['identificacao' => 'a identificação', 'referencia' => 'a referência', 'latitude' => 'a latitude', 'longitude' => 'a longitude'];
    }
}
