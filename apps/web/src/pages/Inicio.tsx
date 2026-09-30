import { Button, Card, Group, Stack, Text, Title } from '@mantine/core';
import { Link } from 'react-router-dom';
import { useAuth } from '../lib/auth';

export function Inicio() {
  const { usuario } = useAuth();
  return (
    <Stack>
      <Title order={2}>Olá, {usuario?.nome}</Title>
      <Card withBorder>
        <Text mb="md">O atendimento começa pelo orçamento. Use os atalhos abaixo:</Text>
        <Group>
          <Button component={Link} to="/orcamentos">
            Novo orçamento
          </Button>
          <Button component={Link} to="/clientes" variant="default">
            Pesquisar cliente
          </Button>
        </Group>
      </Card>
    </Stack>
  );
}
