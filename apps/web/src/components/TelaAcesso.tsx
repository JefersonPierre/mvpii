import { Center, Paper, Stack, Text, Title } from '@mantine/core';
import { ReactNode } from 'react';

export function TelaAcesso({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <Center mih="100vh" bg="gray.1" p="md">
      <Paper withBorder shadow="sm" p="xl" w={400}>
        <Stack>
          <Text size="sm" c="dimmed">
            Laboratório de Análise de Água
          </Text>
          <Title order={2}>{titulo}</Title>
          {children}
        </Stack>
      </Paper>
    </Center>
  );
}
