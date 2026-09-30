import { MantineProvider } from '@mantine/core';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider } from '../lib/auth';
import { Login } from './Login';

function renderizar() {
  return render(
    <MantineProvider>
      <MemoryRouter>
        <AuthProvider>
          <Login />
        </AuthProvider>
      </MemoryRouter>
    </MantineProvider>,
  );
}

describe('Tela de login', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    sessionStorage.clear();
  });

  it('mostra a mensagem da API quando a senha está errada', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ message: 'E-mail ou senha incorretos. Tentativa 1 de 5.' }), { status: 401 }),
    );
    renderizar();
    await userEvent.type(screen.getByLabelText(/e-mail/i), 'gerente@laboratorio.local');
    await userEvent.type(screen.getByLabelText(/senha/i), 'errada');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Tentativa 1 de 5');
  });

  it('guarda o token da sessão quando o login dá certo', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ token: 'abc', usuario: { id: 1, nome: 'Gerente', email: 'g@l.com' } }), {
        status: 200,
      }),
    );
    renderizar();
    await userEvent.type(screen.getByLabelText(/e-mail/i), 'g@l.com');
    await userEvent.type(screen.getByLabelText(/senha/i), 'Senha1234');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    await vi.waitFor(() => expect(sessionStorage.getItem('token')).toBe('abc'));
  });
});
