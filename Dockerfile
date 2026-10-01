# MLC API — production image for Render (runtime: docker)
#
# Lives at the repo ROOT so Render always finds it (default dockerfilePath is
# ./Dockerfile), and uses the repo root as build context. Only backend/
# is copied into the image — the React frontend deploys to Vercel separately.
#
# FrankenPHP = PHP 8.4 + Caddy in one process.

FROM dunglas/frankenphp:1-php8.4

# PHP extensions required by Laravel + Supabase PostgreSQL.
RUN install-php-extensions pdo_pgsql opcache zip

# Production environment variables.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Composer for the dependency install step.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy Composer files first for better Docker layer caching.
COPY backend/composer.json backend/composer.lock ./

# Install production dependencies.
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# Copy the Laravel application.
COPY backend/ /app/

# Generate optimized autoload files and prepare writable directories.
RUN composer dump-autoload --optimize \
    && chmod +x docker/start.sh \
    && chmod -R ug+rwX storage bootstrap/cache

# Run Laravel initialization before starting FrankenPHP.
ENTRYPOINT ["/app/docker/start.sh"]

# Start FrankenPHP after Laravel initialization.
CMD ["frankenphp", "run", "--config", "/app/Caddyfile", "--adapter", "caddyfile"]
