import { Alert, Badge, Center, Loader, Modal, Table, Text } from '@mantine/core';
import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { RegistroHistorico, UsuarioCadastro } from './tipos';

const ACOES: Record<RegistroHistorico['acao'], { rotulo: string; cor: string }> = {
  INCLUSAO: { rotulo: 'Inclusão', cor: 'teal' },
  ALTERACAO: { rotulo: 'Alteração', cor: 'blue' },
  INATIVACAO: { rotulo: 'Inativação', cor: 'red' },
  REATIVACAO: { rotulo: 'Reativação', cor: 'green' },
};

const CAMPOS: Record<string, string> = { nome: 'Nome', email: 'E-mail', ativo: 'Situação' };

function valor(campo: string | null, v: string | null) {
  if (v === null) return <Text c="dimmed">—</Text>;
  if (campo === 'ativo') return v === 'true' ? 'Ativo' : 'Inativo';
  return v;
}

// RN08: histórico de alterações do usuário (quem, quando, valor anterior e novo)
export function ModalHistorico({ usuario, onFechar }: { usuario: UsuarioCadastro | null; onFechar: () => void }) {
  const [registros, setRegistros] = useState<RegistroHistorico[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  useEffect(() => {
    if (!usuario) return;
    setRegistros(null);
    setErro(null);
    api<RegistroHistorico[]>(`/usuarios/${usuario.id}/historico`)
      .then(setRegistros)
      .catch((err: Error) => setErro(err.message));
  }, [usuario]);

  return (
    <Modal opened={Boolean(usuario)} onClose={onFechar} title={`Histórico – ${usuario?.nome ?? ''}`} size="xl">
      {erro && <Alert color="red">{erro}</Alert>}
      {!erro && !registros && (
        <Center p="lg">
          <Loader />
        </Center>
      )}
      {registros?.length === 0 && <Text c="dimmed">Nenhuma alteração registrada.</Text>}
      {registros && registros.length > 0 && (
        <Table.ScrollContainer minWidth={640}>
          <Table striped>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Data e hora</Table.Th>
                <Table.Th>Responsável</Table.Th>
                <Table.Th>Ação</Table.Th>
                <Table.Th>Campo</Table.Th>
                <Table.Th>Valor anterior</Table.Th>
                <Table.Th>Valor novo</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {registros.map((r) => (
                <Table.Tr key={r.id}>
                  <Table.Td>{new Date(r.dataHora).toLocaleString('pt-BR')}</Table.Td>
                  <Table.Td>{r.usuario?.nome ?? '—'}</Table.Td>
                  <Table.Td>
                    <Badge color={ACOES[r.acao].cor} variant="light">
                      {ACOES[r.acao].rotulo}
                    </Badge>
                  </Table.Td>
                  <Table.Td>{r.campo ? (CAMPOS[r.campo] ?? r.campo) : '—'}</Table.Td>
                  <Table.Td>{valor(r.campo, r.valorAnterior)}</Table.Td>
                  <Table.Td>{valor(r.campo, r.valorNovo)}</Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
    </Modal>
  );
}
