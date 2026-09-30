#!/bin/sh
# Início do contêiner na publicação: ajusta as variáveis, prepara o banco e sobe o servidor web.
set -e

# O Render gera um segredo aleatório; o Laravel precisa de uma chave de 32 bytes no formato "base64:...".
case "$APP_KEY" in
    base64:*) ;;
    *) APP_KEY=$(php -r 'echo "base64:".base64_encode(hash("sha256", (string) getenv("APP_KEY"), true));'); export APP_KEY ;;
esac
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan sistema:preparar

# O plano gratuito não tem cron: o agendador (expiração de orçamentos, RN17) roda junto com o servidor.
php artisan schedule:work > /dev/null 2>&1 &

exec frankenphp php-server --listen ":${PORT:-8080}" --root public/
