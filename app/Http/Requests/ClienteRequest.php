<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Support\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// UC03 – dados do cliente ou interessado (RF04, RF05, RF06, RN01, RN02)
class ClienteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Contatos totalmente em branco (linha adicionada e não preenchida) são ignorados.
        $contatos = collect($this->input('contatos', []))
            ->map(fn ($c) => [
                'id' => $c['id'] ?? null,
                'nome' => trim((string) ($c['nome'] ?? '')),
                'cargo' => trim((string) ($c['cargo'] ?? '')) ?: null,
                'telefone' => preg_replace('/\D/', '', (string) ($c['telefone'] ?? '')) ?: null,
                'email' => mb_strtolower(trim((string) ($c['email'] ?? ''))) ?: null,
            ])
            ->filter(fn ($c) => $c['nome'] !== '' || $c['telefone'] || $c['email'])
            ->all();

        $this->merge([
            'documento' => Documento::limpar($this->input('documento')) ?: null,
            'cep' => preg_replace('/\D/', '', (string) $this->input('cep')) ?: null,
            'uf' => strtoupper((string) $this->input('uf')) ?: null,
            'contatos' => $contatos,
        ]);
    }

    public function rules(): array
    {
        // RN01 e RN18: cliente completo exige documento e endereço; interessado, só nome e contato.
        $doCliente = ['nullable', 'required_if:cadastro,cliente', 'string'];

        return [
            'cadastro' => ['required', 'in:cliente,interessado'],
            'tipo_pessoa' => ['required', 'in:F,J'],
            'nome' => ['required', 'string', 'max:150'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'documento' => [...$doCliente, 'max:14'],
            'cep' => [...$doCliente, 'digits:8'],
            'logradouro' => [...$doCliente, 'max:150'],
            'numero' => [...$doCliente, 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => [...$doCliente, 'max:80'],
            'cidade' => [...$doCliente, 'max:80'],
            'uf' => [...$doCliente, Rule::in(Cliente::UFS)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'contatos' => ['required', 'array', 'min:1'],
            'contatos.*.id' => ['nullable', 'integer'],
            'contatos.*.nome' => ['required', 'string', 'max:120'],
            'contatos.*.cargo' => ['nullable', 'string', 'max:80'],
            'contatos.*.telefone' => ['nullable', 'digits_between:10,11'],
            'contatos.*.email' => ['nullable', 'email', 'max:150'],
            'principal' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'contatos.required' => 'Adicione ao menos um contato com telefone ou e-mail.',
            'contatos.*.telefone.digits_between' => 'Informe o telefone com DDD.',
            'cep.digits' => 'O CEP deve ter 8 dígitos.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $this->validarContatos($validator);
                $this->validarDocumento($validator);
            },
        ];
    }

    /** RN02: todo contato tem telefone ou e-mail, e existe exatamente um principal. */
    private function validarContatos(Validator $validator): void
    {
        foreach ($this->input('contatos') as $chave => $contato) {
            if (! $contato['telefone'] && ! $contato['email']) {
                $validator->errors()->add("contatos.$chave.telefone", 'Informe o telefone ou o e-mail do contato.');
            }
        }
        if (! array_key_exists((string) $this->input('principal'), $this->input('contatos'))) {
            $validator->errors()->add('principal', 'Marque o contato principal.');
        }
    }

    /** RN01: dígitos verificadores válidos e documento único entre os clientes. */
    private function validarDocumento(Validator $validator): void
    {
        $documento = $this->input('documento');
        if (! $documento) {
            return;
        }
        $pessoaFisica = $this->input('tipo_pessoa') === 'F';
        if (! Documento::valido($this->input('tipo_pessoa'), $documento)) {
            $validator->errors()->add('documento', $pessoaFisica ? 'CPF inválido: confira os dígitos.' : 'CNPJ inválido: confira os dígitos.');

            return;
        }

        $existente = Cliente::where('documento', $documento)
            ->when($this->route('cliente'), fn ($q, Cliente $atual) => $q->whereKeyNot($atual->id))
            ->first();
        if ($existente) {
            $validator->errors()->add('documento', ($pessoaFisica ? 'CPF' : 'CNPJ').' já cadastrado.');
            // UC03 6b: a tela oferece abrir o cliente existente.
            session()->flash('cliente_existente', ['id' => $existente->id, 'nome' => $existente->nome]);
        }
    }
}
