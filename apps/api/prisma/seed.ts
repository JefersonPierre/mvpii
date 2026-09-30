// Dados iniciais: cria o primeiro usuário para permitir o acesso ao sistema.
import { PrismaClient } from '@prisma/client';
import * as bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  const email = (process.env.ADMIN_EMAIL ?? 'admin@laboratorio.local').toLowerCase();
  const existente = await prisma.usuario.findUnique({ where: { email } });
  if (existente) {
    console.log(`Usuário inicial já existe: ${email}`);
    return;
  }
  await prisma.usuario.create({
    data: {
      nome: process.env.ADMIN_NOME ?? 'Administrador',
      email,
      senhaHash: await bcrypt.hash(process.env.ADMIN_SENHA ?? 'Admin12345', 10),
    },
  });
  console.log(`Usuário inicial criado: ${email}`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
