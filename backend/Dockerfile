# MLC API — production image for Render (runtime: docker).
#
# FrankenPHP (official PHP Foundation image) = PHP 8.4 + Caddy in one process.
# Serves Laravel from /app/public and honours Render's $PORT.

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

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chmod +x docker/start.sh \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8080

CMD ["/app/docker/start.sh"]
