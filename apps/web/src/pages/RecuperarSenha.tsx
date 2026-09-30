import { Alert, Anchor, Button, TextInput } from '@mantine/core';
import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { TelaAcesso } from '../components/TelaAcesso';
import { api } from '../lib/api';

// RF02 – Recuperar senha por e-mail
export function RecuperarSenha() {
  const [email, setEmail] = useState('');
  const [mensagem, setMensagem] = useState<string | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    setErro(null);
    setEnviando(true);
    try {
      const r = await api<{ mensagem: string }>('/auth/recuperar-senha', { metodo: 'POST', corpo: { email } });
      setMensagem(`${r.mensagem} O link vale por 1 hora.`);
    } catch (err) {
      setErro((err as Error).message);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <TelaAcesso titulo="Recuperar senha">
      {mensagem ? (
        <Alert color="green">{mensagem}</Alert>
      ) : (
        <form onSubmit={enviar}>
          {erro && (
            <Alert color="red" mb="md">
              {erro}
            </Alert>
          )}
          <TextInput
            label="E-mail cadastrado"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <Button type="submit" fullWidth mt="lg" loading={enviando}>
            Enviar link
          </Button>
        </form>
      )}
      <Anchor component={Link} to="/login" size="sm">
        Voltar para o login
      </Anchor>
    </TelaAcesso>
  );
}
