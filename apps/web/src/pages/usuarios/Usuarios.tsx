import {
  Alert,
  Badge,
  Button,
  Card,
  Center,
  Group,
  Loader,
  Modal,
  SegmentedControl,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
} from '@mantine/core';
import { useDebouncedValue } from '@mantine/hooks';
import { notifications } from '@mantine/notifications';
import { useCallback, useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ModalHistorico } from './ModalHistorico';
import { ModalUsuario } from './ModalUsuario';
import { Situacao, UsuarioCadastro } from './tipos';

// UC02 – Cadastrar usuários (RF03, RN08, RN10, RN11)
export function Usuarios() {
  const { usuario: eu } = useAuth();
  const [busca, setBusca] = useState('');
  const [buscaAtrasada] = useDebouncedValue(busca, 300);
  const [situacao, setSituacao] = useState<Situacao>('ativos');
  const [usuarios, setUsuarios] = useState<UsuarioCadastro[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  const [formAberto, setFormAberto] = useState(false);
  const [emEdicao, setEmEdicao] = useState<UsuarioCadastro | null>(null);
  const [emHistorico, setEmHistorico] = useState<UsuarioCadastro | null>(null);
  const [aInativar, setAInativar] = useState<UsuarioCadastro | null>(null);
  const [alterandoSituacao, setAlterandoSituacao] = useState(false);

  const carregar = useCallback(async () => {
    setErro(null);
    const params = new URLSearchParams({ situacao });
    if (buscaAtrasada.trim()) params.set('busca', buscaAtrasada.trim());
    try {
      setUsuarios(await api<UsuarioCadastro[]>(`/usuarios?${params}`));
    } catch (err) {
      setErro((err as Error).message);
    }
  }, [situacao, buscaAtrasada]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  function abrirNovo() {
    setEmEdicao(null);
    setFormAberto(true);
  }

  function abrirEdicao(u: UsuarioCadastro) {
    setEmEdicao(u);
    setFormAberto(true);
  }

  function aoSalvar(u: UsuarioCadastro, novo: boolean) {
    setFormAberto(false);
    notifications.show({
      color: 'teal',
      message: novo ? `Usuário cadastrado. O link para criar a senha foi enviado para ${u.email}.` : 'Usuário atualizado.',
    });
    carregar();
  }

  // RN10: usuário inativo não acessa o sistema; o cadastro é mantido e pode ser reativado.
  async function alterarSituacao(u: UsuarioCadastro, ativo: boolean) {
    setAlterandoSituacao(true);
    try {
      await api(`/usuarios/${u.id}/situacao`, { metodo: 'PATCH', corpo: { ativo } });
      notifications.show({ color: 'teal', message: ativo ? 'Usuário reativado.' : 'Usuário inativado.' });
      setAInativar(null);
      carregar();
    } catch (err) {
      notifications.show({ color: 'red', message: (err as Error).message });
    } finally {
      setAlterandoSituacao(false);
    }
  }

  return (
    <Stack>
      <Group justify="space-between">
        <Title order={2}>Usuários</Title>
        <Button onClick={abrirNovo}>Novo usuário</Button>
      </Group>

      <Card withBorder>
        <Group align="flex-end" mb="md">
          <TextInput
            label="Buscar"
            placeholder="Nome ou e-mail"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            style={{ flexGrow: 1 }}
          />
          <SegmentedControl
            aria-label="Situação"
            value={situacao}
            onChange={(v) => setSituacao(v as Situacao)}
            data={[
              { label: 'Ativos', value: 'ativos' },
              { label: 'Inativos', value: 'inativos' },
              { label: 'Todos', value: 'todos' },
            ]}
          />
        </Group>

        {erro && <Alert color="red">{erro}</Alert>}
        {!erro && !usuarios && (
          <Center p="lg">
            <Loader />
          </Center>
        )}
        {usuarios?.length === 0 && <Text c="dimmed">Nenhum usuário encontrado.</Text>}
        {usuarios && usuarios.length > 0 && (
          <Table.ScrollContainer minWidth={640}>
            <Table highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Nome</Table.Th>
                  <Table.Th>E-mail</Table.Th>
                  <Table.Th>Situação</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {usuarios.map((u) => (
                  <Table.Tr key={u.id}>
                    <Table.Td>{u.nome}</Table.Td>
                    <Table.Td>{u.email}</Table.Td>
                    <Table.Td>
                      <Badge color={u.ativo ? 'teal' : 'gray'} variant="light">
                        {u.ativo ? 'Ativo' : 'Inativo'}
                      </Badge>
                    </Table.Td>
                    <Table.Td>
                      <Group gap="xs" justify="flex-end" wrap="nowrap">
                        <Button size="xs" variant="default" onClick={() => abrirEdicao(u)}>
                          Editar
                        </Button>
                        <Button size="xs" variant="default" onClick={() => setEmHistorico(u)}>
                          Histórico
                        </Button>
                        {u.ativo ? (
                          <Button
                            size="xs"
                            variant="light"
                            color="red"
                            disabled={u.id === eu?.id}
                            title={u.id === eu?.id ? 'Você não pode inativar o seu próprio usuário.' : undefined}
                            onClick={() => setAInativar(u)}
                          >
                            Inativar
                          </Button>
                        ) : (
                          <Button
                            size="xs"
                            variant="light"
                            loading={alterandoSituacao}
                            onClick={() => alterarSituacao(u, true)}
                          >
                            Reativar
                          </Button>
                        )}
                      </Group>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
      </Card>

      <ModalUsuario aberto={formAberto} usuario={emEdicao} onFechar={() => setFormAberto(false)} onSalvo={aoSalvar} />
      <ModalHistorico usuario={emHistorico} onFechar={() => setEmHistorico(null)} />

      <Modal opened={Boolean(aInativar)} onClose={() => setAInativar(null)} title="Inativar usuário">
        <Text mb="lg">
          <b>{aInativar?.nome}</b> não poderá mais entrar no sistema. O cadastro e o histórico são mantidos e o
          usuário pode ser reativado depois.
        </Text>
        <Group justify="flex-end">
          <Button variant="default" onClick={() => setAInativar(null)}>
            Cancelar
          </Button>
          <Button color="red" loading={alterandoSituacao} onClick={() => aInativar && alterarSituacao(aInativar, false)}>
            Inativar
          </Button>
        </Group>
      </Modal>
    </Stack>
  );
}
