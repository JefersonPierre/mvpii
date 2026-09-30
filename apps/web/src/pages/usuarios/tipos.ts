export interface UsuarioCadastro {
  id: number;
  nome: string;
  email: string;
  ativo: boolean;
  criadoEm: string;
}

export type Situacao = 'ativos' | 'inativos' | 'todos';

// RN08: cada linha é um campo alterado em uma inclusão, alteração, inativação ou reativação.
export interface RegistroHistorico {
  id: number;
  acao: 'INCLUSAO' | 'ALTERACAO' | 'INATIVACAO' | 'REATIVACAO';
  campo: string | null;
  valorAnterior: string | null;
  valorNovo: string | null;
  dataHora: string;
  usuario: { nome: string } | null;
}
