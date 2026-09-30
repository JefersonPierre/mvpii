import { AppShell, Button, Group, NavLink, Stack, Text, Title } from '@mantine/core';
import { NavLink as LinkRota, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../lib/auth';

const MENU = [
  { rotulo: 'Início', para: '/' },
  { rotulo: 'Orçamentos', para: '/orcamentos' },
  { rotulo: 'Clientes', para: '/clientes' },
  { rotulo: 'Catálogo técnico', para: '/catalogo' },
  { rotulo: 'Usuários', para: '/usuarios' },
  { rotulo: 'Configurações', para: '/configuracoes' },
];

export function Layout() {
  const { usuario, sair } = useAuth();
  const { pathname } = useLocation();

  return (
    <AppShell navbar={{ width: 230, breakpoint: 'sm' }} padding="lg">
      <AppShell.Navbar p="md" bg="dark.7">
        <Title order={4} c="white" mb="lg">
          Laboratório de Análise de Água
        </Title>
        <Stack gap={4} style={{ flexGrow: 1 }}>
          {MENU.map((m) => (
            <NavLink
              key={m.para}
              component={LinkRota}
              to={m.para}
              label={m.rotulo}
              active={m.para === '/' ? pathname === '/' : pathname.startsWith(m.para)}
              variant="filled"
              c="gray.2"
            />
          ))}
        </Stack>
        <Group justify="space-between" mt="md">
          <Text size="sm" c="gray.4">
            {usuario?.nome}
          </Text>
          <Button size="xs" variant="subtle" color="gray" onClick={sair}>
            Sair
          </Button>
        </Group>
      </AppShell.Navbar>
      <AppShell.Main bg="gray.0">
        <Outlet />
      </AppShell.Main>
    </AppShell>
  );
}
