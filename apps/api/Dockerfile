# Imagem de desenvolvimento da API (usada pelo docker-compose.yml).
FROM node:22-alpine
WORKDIR /app
COPY package.json package-lock.json ./
COPY apps/api/package.json apps/api/package.json
COPY apps/web/package.json apps/web/package.json
RUN npm ci -w apps/api --include-workspace-root=false
COPY apps/api apps/api
WORKDIR /app/apps/api
RUN npx prisma generate
EXPOSE 3000
# Aplica as migrações, carrega os dados iniciais e sobe a API com recarga automática.
CMD ["sh", "-c", "npx prisma migrate deploy && npx prisma db seed && npm run start:dev"]
