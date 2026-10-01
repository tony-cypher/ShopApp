# MLC API — production image for Render (runtime: docker).
#
# Lives at the repo ROOT so Render always finds it (default dockerfilePath is
# ./Dockerfile), and uses the repo root as build context. Only backend/ is
# copied into the image — the React frontend deploys to Vercel separately.
#
# FrankenPHP (official PHP Foundation image) = PHP 8.4 + Caddy in one process.
# Serves Laravel from /app/backend/public and honours Render's $PORT.

FROM dunglas/frankenphp:1-php8.4

# PHP extensions Laravel + Supabase Postgres need (mbstring/openssl/curl are built in).
RUN install-php-extensions pdo_pgsql opcache zip

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Composer for the dependency install step.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Layer cache: composer files first, then the rest of the backend.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY backend/ /app/

RUN composer dump-autoload --optimize \
    && chmod +x docker/start.sh \
    && chmod -R ug+rwX storage bootstrap/cache

ENTRYPOINT ["/app/docker/start.sh"]

CMD ["frankenphp", "run", "--config", "/app/Caddyfile", "--adapter", "caddyfile"]
