<?php

namespace App\Support;

// MOD07 – Perfis e permissões (RF25, RF26, RN23). Os quatro perfis e a matriz são fixos (seção 7.4 da documentação).
final class Perfis
{
    public const ADMINISTRADOR = 'ADMINISTRADOR';

    public const RECEPCAO = 'RECEPCAO';

    public const COLETOR = 'COLETOR';

    public const ANALISTA = 'ANALISTA';

    public const NOMES = [
        self::ADMINISTRADOR => 'Administrador',
        self::RECEPCAO => 'Recepção',
        self::COLETOR => 'Coletor',
        self::ANALISTA => 'Analista',
    ];

    public const DESCRICOES = [
        self::ADMINISTRADOR => 'Acesso a todas as funcionalidades, inclusive usuários e configurações.',
        self::RECEPCAO => 'Clientes, orçamentos, agendamento e roteiro das coletas, conflitos de sincronização, envio e consulta de laudos.',
        self::COLETOR => 'Agenda, roteiro e registro (também pelo celular) somente das coletas atribuídas a ele.',
        self::ANALISTA => 'Recebimento das amostras, lançamento e revisão de resultados e consulta de laudos.',
    ];

    /**
     * Matriz de permissões (seção 7.4): permissão => perfis que a têm. As permissões de um usuário são a soma das
     * permissões dos seus perfis (RN23). "Próprias" (coletor) é tratada nas regras de cada coleta (ColetaPolicy).
     */
    public const PERMISSOES = [
        'usuarios' => [self::ADMINISTRADOR],
        'configuracoes' => [self::ADMINISTRADOR],
        'catalogo.consultar' => [self::ADMINISTRADOR, self::RECEPCAO, self::COLETOR, self::ANALISTA],
        'catalogo.editar' => [self::ADMINISTRADOR],
        'clientes.consultar' => [self::ADMINISTRADOR, self::RECEPCAO, self::COLETOR, self::ANALISTA],
        'clientes.editar' => [self::ADMINISTRADOR, self::RECEPCAO],
        'orcamentos' => [self::ADMINISTRADOR, self::RECEPCAO],
        'coletas.agendar' => [self::ADMINISTRADOR, self::RECEPCAO],
        'coletas.agenda' => [self::ADMINISTRADOR, self::RECEPCAO, self::COLETOR, self::ANALISTA],
        'coletas.registrar' => [self::ADMINISTRADOR, self::COLETOR],
        'amostras.receber' => [self::ADMINISTRADOR, self::ANALISTA],
        'amostras.consultar' => [self::ADMINISTRADOR, self::RECEPCAO, self::COLETOR, self::ANALISTA],
        'historico' => [self::ADMINISTRADOR, self::RECEPCAO],
        // Entregável 3 (seção 7.4, linhas acrescentadas)
        'coletas.roteiro' => [self::ADMINISTRADOR, self::RECEPCAO, self::COLETOR, self::ANALISTA],
        'coletas.roteiro.ordenar' => [self::ADMINISTRADOR, self::RECEPCAO, self::COLETOR],
        'coletas.conflitos' => [self::ADMINISTRADOR, self::RECEPCAO],
        'resultados.lancar' => [self::ADMINISTRADOR, self::ANALISTA],
        'resultados.liberar' => [self::ADMINISTRADOR, self::ANALISTA], // com separação de funções (RN53)
        'laudos.emitir' => [self::ADMINISTRADOR],
        'laudos.enviar' => [self::ADMINISTRADOR, self::RECEPCAO],
        'laudos.consultar' => [self::ADMINISTRADOR, self::RECEPCAO, self::ANALISTA],
    ];

    /** Perfis que veem todas as coletas e amostras; quem só tem o perfil Coletor vê apenas as próprias (RN36). */
    public const VEEM_TODAS_AS_COLETAS = [self::ADMINISTRADOR, self::RECEPCAO, self::ANALISTA];

    public const MENSAGEM_ACESSO_NEGADO = 'Você não tem permissão para acessar esta funcionalidade.';

    /**
     * Ordena e remove repetições, para comparar e gravar sempre na mesma ordem.
     *
     * @param  iterable<string>  $perfis
     * @return list<string>
     */
    public static function normalizar(iterable $perfis): array
    {
        $lista = collect($perfis)->unique()->all();

        return array_values(array_filter(array_keys(self::NOMES), fn ($p) => in_array($p, $lista, true)));
    }

    /** @param  iterable<string>  $perfis */
    public static function nomes(iterable $perfis): string
    {
        return collect(self::normalizar($perfis))->map(fn ($p) => self::NOMES[$p])->implode(', ');
    }
}
