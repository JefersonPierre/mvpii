# Imagem usada na publicação (Render). Três etapas: dependências PHP, CSS/JavaScript e a aplicação,
# servida pelo FrankenPHP (PHP 8.4 com servidor web embutido).

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# O Tailwind lê as views de paginação do Laravel, que ficam no vendor.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions pdo_pgsql intl gd zip opcache
# O binário vem com permissão para usar portas abaixo de 1024 (file capability), e o Render recusa executar
# binários assim ("Operation not permitted"). O serviço usa a porta 10000, então a permissão é retirada.
RUN apt-get update && apt-get install -y --no-install-recommends libcap2-bin \
    && setcap -r /usr/local/bin/frankenphp \
    && apt-get purge -y libcap2-bin && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
        storage/app/private bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

CMD ["sh", "docker/iniciar.sh"]
