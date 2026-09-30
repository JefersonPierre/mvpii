# mvpii – Laboratório de Análise de Água

MVP do TCC: sistema para um laboratório de análise de qualidade da água (água potável e efluentes).
A documentação técnica do Entregável 1 (Cadastros e Orçamentos) está em [`docs/entregavel-1`](docs/entregavel-1).

## Como rodar

Pré-requisitos: **Git** e **Docker** (Docker Desktop no Windows/macOS ou Docker Engine no Linux). Nada mais precisa ser instalado.

```bash
git clone https://github.com/JefersonPierre/mvpii.git
cd mvpii
cp .env.example .env      # no Windows (PowerShell): copy .env.example .env
docker compose up --build
```

Na primeira vez o Docker baixa as imagens e instala as dependências; depois sobe tudo em poucos segundos.

| Serviço | Endereço |
| --- | --- |
| Sistema (front-end) | http://localhost:5173 |
| API | http://localhost:3000 |
| Documentação da API (Swagger) | http://localhost:3000/docs |
| Caixa de e-mail simulada (Mailpit) | http://localhost:8025 |

Usuário inicial: `admin@laboratorio.local` / senha `Admin12345` (definidos no `.env`).

Os e-mails enviados pelo sistema (por exemplo, recuperação de senha) **não saem de verdade**: aparecem na caixa do Mailpit.

Para parar: `Ctrl+C` ou `docker compose down`. Para apagar também o banco: `docker compose down -v`.

## Estrutura

```
apps/
  api/   API REST em NestJS + Prisma (PostgreSQL)
  web/   Front-end em React + Vite + Mantine
docs/    Documentação técnica
docker-compose.yml  Banco, e-mail simulado, API e front-end
```

## Stack

TypeScript em todo o projeto · React + Vite + Mantine · NestJS · PostgreSQL + Prisma · JWT + bcrypt ·
Nodemailer + Mailpit · Swagger · Jest e Vitest · Docker Compose.

## Desenvolvimento

Com o Docker rodando, as alterações em `apps/api/src` e `apps/web/src` recarregam sozinhas.

Comandos úteis (na raiz, com Node 22 instalado, opcional):

```bash
npm install          # dependências para o editor e os testes
npm test             # testes da API e do front-end
npm run lint         # verificação de tipos
npm run build        # build de produção
```

Alterou o esquema do banco (`apps/api/prisma/schema.prisma`)? Gere a migração com o banco do Docker no ar:

```bash
cd apps/api
DATABASE_URL="postgresql://laboratorio:laboratorio@localhost:5432/laboratorio?schema=public" npx prisma migrate dev --name descricao
```

## Produção (final do projeto)

Os mesmos containers rodam em qualquer servidor com Docker. Basta ajustar o `.env`: trocar `JWT_SECRET`,
apontar `SMTP_*` para um serviço de e-mail real e configurar HTTPS e a cópia de segurança do banco.

## Andamento do Entregável 1

- [x] Estrutura do projeto e ambiente Docker
- [x] MOD01 – Login e recuperação de senha (RF01, RF02)
- [x] MOD01 – Cadastro de usuários (RF03)
- [ ] MOD02 – Clientes (RF04–RF08)
- [ ] MOD03 – Pontos de coleta (RF09–RF11)
- [ ] MOD04 – Catálogo técnico (RF12–RF16)
- [ ] MOD05 – Orçamentos (RF17–RF22)
- [ ] MOD06 – Consulta e histórico (RF23–RF24)
