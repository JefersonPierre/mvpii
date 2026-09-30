import { MantineProvider } from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider } from '../../lib/auth';
import { Usuarios } from './Usuarios';

const MARIA = { id: 2, nome: 'Maria Souza', email: 'maria@laboratorio.local', ativo: true, criadoEm: '2026-09-01' };
const JOAO = { id: 3, nome: 'João Lima', email: 'joao@laboratorio.local', ativo: false, criadoEm: '2026-09-02' };

function resposta(corpo: unknown, status = 200) {
  return new Response(JSON.stringify(corpo), { status });
}

function renderizar() {
  return render(
    <MantineProvider>
      <Notifications />
      <MemoryRouter>
        <AuthProvider>
          <Usuarios />
        </AuthProvider>
      </MemoryRouter>
    </MantineProvider>,
  );
}

describe('Tela de usuários', () => {
  afterEach(() => vi.restoreAllMocks());

  it('lista os usuários ativos e troca o filtro de situação', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementation(async (url) => resposta(String(url).includes('situacao=todos') ? [JOAO, MARIA] : [MARIA]));
    renderizar();

    expect(await screen.findByText('Maria Souza')).toBeInTheDocument();
    expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/usuarios?situacao=ativos'), expect.anything());

    await userEvent.click(screen.getByText('Todos'));
    expect(await screen.findByText('João Lima')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reativar' })).toBeInTheDocument();
  });

  it('cadastra um novo usuário e mostra o erro de e-mail duplicado (RN11)', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(async (_url, init) =>
      init?.method === 'POST' ? resposta({ message: 'E-mail já cadastrado.' }, 409) : resposta([MARIA]),
    );
    renderizar();
    await screen.findByText('Maria Souza');

    await userEvent.click(screen.getByRole('button', { name: 'Novo usuário' }));
    const modal = await screen.findByRole('dialog');
    await userEvent.type(within(modal).getByLabelText(/nome/i), 'Maria');
    await userEvent.type(within(modal).getByLabelText(/e-mail/i), 'maria@laboratorio.local');
    await userEvent.click(within(modal).getByRole('button', { name: 'Cadastrar' }));

    expect(await within(modal).findByRole('alert')).toHaveTextContent('E-mail já cadastrado.');
    const post = fetch.mock.calls.find(([, init]) => init?.method === 'POST');
    expect(JSON.parse(String(post?.[1]?.body))).toEqual({ nome: 'Maria', email: 'maria@laboratorio.local' });
  });

  it('pede confirmação antes de inativar (RN10)', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementation(async (_url, init) => resposta(init?.method === 'PATCH' ? { ...MARIA, ativo: false } : [MARIA]));
    renderizar();
    await screen.findByText('Maria Souza');

    await userEvent.click(screen.getByRole('button', { name: 'Inativar' }));
    const modal = await screen.findByRole('dialog');
    await userEvent.click(within(modal).getByRole('button', { name: 'Inativar' }));

    await vi.waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/usuarios/2/situacao'),
        expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ ativo: false }) }),
      ),
    );
  });

  it('mostra o histórico com valor anterior e novo (RN08)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (url) =>
      String(url).includes('/historico')
        ? resposta([
            {
              id: 1,
              acao: 'ALTERACAO',
              campo: 'email',
              valorAnterior: 'maria@antigo.local',
              valorNovo: 'maria@laboratorio.local',
              dataHora: '2026-09-10T12:00:00Z',
              usuario: { nome: 'Administrador' },
            },
          ])
        : resposta([MARIA]),
    );
    renderizar();
    await screen.findByText('Maria Souza');

    await userEvent.click(screen.getByRole('button', { name: 'Histórico' }));
    const modal = await screen.findByRole('dialog');
    expect(await within(modal).findByText('maria@antigo.local')).toBeInTheDocument();
    expect(within(modal).getByText('Alteração')).toBeInTheDocument();
    expect(within(modal).getByText('Administrador')).toBeInTheDocument();
  });
});
