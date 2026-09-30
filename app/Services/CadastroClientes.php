<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Contato;
use Illuminate\Support\Facades\DB;

// UC03 – grava cliente e contatos numa transação, com o histórico de alterações (RN08).
class CadastroClientes
{
    public const ENTIDADE = 'CLIENTE';

    public const CAMPOS_AUDITADOS = ['interessado', 'tipo_pessoa', 'nome', 'nome_fantasia', 'documento', 'cep',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'observacoes', 'ativo'];

    public function __construct(private readonly Auditoria $auditoria) {}

    /**
     * @param  array<string, mixed>  $dados  dados validados pelo ClienteRequest
     */
    public function salvar(Cliente $cliente, array $dados): Cliente
    {
        return DB::transaction(function () use ($cliente, $dados) {
            $novo = ! $cliente->exists;
            $antes = $novo ? [] : $cliente->only(self::CAMPOS_AUDITADOS);

            $cliente->fill([
                ...collect($dados)->only(self::CAMPOS_AUDITADOS)->all(),
                'interessado' => $dados['cadastro'] === 'interessado',
                // Pessoa física não tem nome fantasia.
                'nome_fantasia' => $dados['tipo_pessoa'] === 'J' ? ($dados['nome_fantasia'] ?? null) : null,
            ])->save();

            $this->auditoria->registrar(
                self::ENTIDADE, $cliente->id, $novo ? Auditoria::INCLUSAO : Auditoria::ALTERACAO,
                $antes, $cliente->only(self::CAMPOS_AUDITADOS), self::CAMPOS_AUDITADOS,
            );
            $this->sincronizarContatos($cliente, $dados['contatos'], (string) $dados['principal'], $novo);

            return $cliente;
        });
    }

    /** RN04 (inativar) e reativar. Os pontos de coleta serão inativados junto quando o MOD03 existir. */
    public function alterarSituacao(Cliente $cliente, bool $ativo): void
    {
        if ($cliente->ativo === $ativo) {
            return;
        }
        DB::transaction(function () use ($cliente, $ativo) {
            $antes = $cliente->only(self::CAMPOS_AUDITADOS);
            $cliente->update(['ativo' => $ativo]);
            $this->auditoria->registrar(
                self::ENTIDADE, $cliente->id, $ativo ? Auditoria::REATIVACAO : Auditoria::INATIVACAO,
                $antes, $cliente->only(self::CAMPOS_AUDITADOS), self::CAMPOS_AUDITADOS,
            );
        });
    }

    /**
     * Inclui, altera e remove contatos conforme o formulário. Contatos não têm vínculos, então podem ser removidos;
     * cada mudança fica no histórico do cliente com um resumo do contato.
     *
     * @param  array<string|int, array<string, mixed>>  $contatos
     */
    private function sincronizarContatos(Cliente $cliente, array $contatos, string $principal, bool $clienteNovo): void
    {
        $existentes = $cliente->contatos()->get()->keyBy('id');
        $mantidos = [];
        $acao = $clienteNovo ? Auditoria::INCLUSAO : Auditoria::ALTERACAO;

        foreach ($contatos as $chave => $dados) {
            $contato = isset($dados['id']) ? $existentes->get((int) $dados['id']) : null;
            $antes = $contato?->resumo();
            $contato ??= new Contato(['cliente_id' => $cliente->id]);
            $contato->fill([
                'nome' => $dados['nome'],
                'cargo' => $dados['cargo'],
                'telefone' => $dados['telefone'],
                'email' => $dados['email'],
                'principal' => (string) $chave === $principal,
            ]);
            $contato->cliente()->associate($cliente)->save();
            $mantidos[] = $contato->id;

            $this->registrarContato($cliente, $acao, $contato->nome, $antes, $contato->resumo());
        }

        foreach ($existentes->except($mantidos) as $removido) {
            $this->registrarContato($cliente, Auditoria::ALTERACAO, $removido->nome, $removido->resumo(), null);
            $removido->delete();
        }
    }

    private function registrarContato(Cliente $cliente, string $acao, string $nome, ?string $antes, ?string $depois): void
    {
        $campo = mb_substr("Contato {$nome}", 0, 120);
        $this->auditoria->registrar(self::ENTIDADE, $cliente->id, $acao, [$campo => $antes], [$campo => $depois], [$campo]);
    }
}
