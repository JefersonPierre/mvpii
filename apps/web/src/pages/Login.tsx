import { Alert, Anchor, Button, PasswordInput, TextInput } from '@mantine/core';
import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { TelaAcesso } from '../components/TelaAcesso';
import { useAuth } from '../lib/auth';

// UC01 – Autenticar usuário (RF01, RN09, RN10)
export function Login() {
  const { entrar } = useAuth();
  const navegar = useNavigate();
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    setErro(null);
    setEnviando(true);
    try {
      await entrar(email, senha);
      navegar('/', { replace: true });
    } catch (err) {
      setErro((err as Error).message);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <TelaAcesso titulo="Entrar">
      <form onSubmit={enviar}>
        {erro && (
          <Alert color="red" mb="md" role="alert">
            {erro}
          </Alert>
        )}
        <TextInput label="E-mail" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
        <PasswordInput
          label="Senha"
          required
          mt="sm"
          value={senha}
          onChange={(e) => setSenha(e.target.value)}
          error={Boolean(erro)}
        />
        <Button type="submit" fullWidth mt="lg" loading={enviando}>
          Entrar
        </Button>
      </form>
      <Anchor component={Link} to="/recuperar-senha" size="sm">
        Esqueci minha senha
      </Anchor>
    </TelaAcesso>
  );
}
