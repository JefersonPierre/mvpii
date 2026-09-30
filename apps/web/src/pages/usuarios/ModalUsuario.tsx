import { Alert, Button, Group, Modal, Stack, TextInput } from '@mantine/core';
import { FormEvent, useEffect, useState } from 'react';
import { api } from '../../lib/api';
import { UsuarioCadastro } from './tipos';

interface Props {
  aberto: boolean;
  /** Usuário em edição; `null` para um novo cadastro. */
  usuario: UsuarioCadastro | null;
  onFechar: () => void;
  onSalvo: (usuario: UsuarioCadastro, novo: boolean) => void;
}

// UC02 – incluir e editar usuário (RF03, RN11)
export function ModalUsuario({ aberto, usuario, onFechar, onSalvo }: Props) {
  const [nome, setNome] = useState('');
  const [email, setEmail] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    if (!aberto) return;
    setNome(usuario?.nome ?? '');
    setEmail(usuario?.email ?? '');
    setErro(null);
  }, [aberto, usuario]);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    setErro(null);
    setEnviando(true);
    try {
      const salvo = await api<UsuarioCadastro>(usuario ? `/usuarios/${usuario.id}` : '/usuarios', {
        metodo: usuario ? 'PATCH' : 'POST',
        corpo: { nome, email },
      });
      onSalvo(salvo, !usuario);
    } catch (err) {
      setErro((err as Error).message);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <Modal opened={aberto} onClose={onFechar} title={usuario ? 'Editar usuário' : 'Novo usuário'}>
      <form onSubmit={enviar}>
        <Stack>
          {erro && (
            <Alert color="red" role="alert">
              {erro}
            </Alert>
          )}
          <TextInput label="Nome" required maxLength={120} value={nome} onChange={(e) => setNome(e.target.value)} />
          <TextInput
            label="E-mail"
            type="email"
            required
            maxLength={150}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            description={usuario ? undefined : 'O usuário recebe neste e-mail o link para criar a própria senha.'}
          />
          <Group justify="flex-end">
            <Button variant="default" onClick={onFechar}>
              Cancelar
            </Button>
            <Button type="submit" loading={enviando}>
              {usuario ? 'Salvar' : 'Cadastrar'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
