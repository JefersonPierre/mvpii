import { Alert, Anchor, Button, PasswordInput } from '@mantine/core';
import { FormEvent, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { TelaAcesso } from '../components/TelaAcesso';
import { api } from '../lib/api';

// RF02 – criar nova senha a partir do link recebido por e-mail
export function NovaSenha() {
  const [params] = useSearchParams();
  const [senha, setSenha] = useState('');
  const [confirmacao, setConfirmacao] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [concluido, setConcluido] = useState(false);
  const [enviando, setEnviando] = useState(false);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    if (senha !== confirmacao) {
      setErro('As senhas não conferem.');
      return;
    }
    setErro(null);
    setEnviando(true);
    try {
      await api('/auth/redefinir-senha', {
        metodo: 'POST',
        corpo: { token: params.get('token') ?? '', novaSenha: senha },
      });
      setConcluido(true);
    } catch (err) {
      setErro((err as Error).message);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <TelaAcesso titulo="Criar nova senha">
      {concluido ? (
        <Alert color="green">Senha alterada. Entre com a nova senha.</Alert>
      ) : (
        <form onSubmit={enviar}>
          {erro && (
            <Alert color="red" mb="md">
              {erro}
            </Alert>
          )}
          <PasswordInput
            label="Nova senha"
            description="Mínimo de 8 caracteres, com letras e números."
            required
            value={senha}
            onChange={(e) => setSenha(e.target.value)}
          />
          <PasswordInput
            label="Confirme a nova senha"
            required
            mt="sm"
            value={confirmacao}
            onChange={(e) => setConfirmacao(e.target.value)}
          />
          <Button type="submit" fullWidth mt="lg" loading={enviando}>
            Salvar nova senha
          </Button>
        </form>
      )}
      <Anchor component={Link} to="/login" size="sm">
        Ir para o login
      </Anchor>
    </TelaAcesso>
  );
}
