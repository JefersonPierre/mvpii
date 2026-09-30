import { Alert, Stack, Title } from '@mantine/core';

export function EmConstrucao({ titulo }: { titulo: string }) {
  return (
    <Stack>
      <Title order={2}>{titulo}</Title>
      <Alert color="blue">Esta tela será construída nas próximas etapas do Entregável 1.</Alert>
    </Stack>
  );
}
