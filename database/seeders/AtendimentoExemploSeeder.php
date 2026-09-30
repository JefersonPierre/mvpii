<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Pacote;
use App\Models\Usuario;
use App\Services\CadastroOrcamentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Atendimento de exemplo: um cliente com contato e ponto de coleta, e um orçamento em rascunho
 * com o pacote de potabilidade. Usa o catálogo do CatalogoExemploSeeder, que precisa rodar antes.
 * Pode ser executado mais de uma vez: se o cliente já existe, não faz nada.
 */
class AtendimentoExemploSeeder extends Seeder
{
    public function run(CadastroOrcamentos $orcamentos): void
    {
        $pacote = Pacote::where('nome', 'Potabilidade básica')->first();
        if (! $pacote || Cliente::where('documento', '11222333000181')->exists()) {
            return;
        }

        $cliente = Cliente::create([
            'tipo_pessoa' => 'J',
            'nome' => 'Condomínio Residencial Primavera',
            'nome_fantasia' => 'Residencial Primavera',
            'documento' => '11222333000181',
            'cep' => '13010000',
            'logradouro' => 'Rua Barão de Jaguara',
            'numero' => '1000',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ]);
        $contato = $cliente->contatos()->create([
            'nome' => 'Carlos Pereira',
            'cargo' => 'Síndico',
            'telefone' => '1932320000',
            'email' => 'sindico@residencialprimavera.com.br',
            'principal' => true,
        ]);
        $cliente->pontosColeta()->create([
            'identificacao' => 'Reservatório superior – Bloco A',
            'tipo_amostra_id' => $pacote->tipo_amostra_id,
            'logradouro' => 'Rua Barão de Jaguara',
            'numero' => '1000',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
        ]);

        // O seeder libera todos os campos; o orçamento passa pelo mesmo cadastro das telas, que conta com o $fillable.
        Model::reguard();
        // O orçamento registra quem o criou: usa o primeiro usuário (o administrador).
        Auth::setUser(Usuario::query()->orderBy('id')->firstOrFail());
        $orcamentos->salvarRascunho(new Orcamento, [
            'cliente_id' => $cliente->id,
            'contato_id' => $contato->id,
            'tipo_amostra_id' => $pacote->tipo_amostra_id,
            'itens' => [['referencia' => 'PACOTE:'.$pacote->id, 'quantidade' => 2]],
            'taxa_coleta' => 80,
            'desconto_percentual' => 0,
            'validade_dias' => 30,
            'condicoes_pagamento' => 'Boleto em 30 dias',
            'observacoes' => 'Análise semestral de potabilidade.',
        ]);
    }
}
