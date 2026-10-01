#!/bin/sh

# Container boot script (Render Docker runtime).
# 1. caches config/routes/views   2. migrates (with retry)   3. optionally seeds
# 4. hands off to FrankenPHP (the image CMD) on $PORT.

set -eu

# /app in the container; derived from this script's location so the script
# also works outside the container.
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

# Fail fast with a clear message instead of serving 500s for hours.
if [ -z "${APP_KEY:-}" ]; then
    echo "!! APP_KEY is not set. Generate one with 'php artisan key:generate --show'"
    echo "   and add it to the service's environment variables (Render dashboard)."
    exit 1
fi

echo "==> Caching configuration for production"

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Running migrations"

attempt=1

until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge 5 ]; then
        echo "!! Migrations still failing after $attempt attempts — starting anyway"
        break
    fi

    attempt=$((attempt + 1))

    echo "   database not reachable yet — retrying in 5s..."

    sleep 5
done

if [ "${RUN_SEED:-false}" = "true" ]; then
    echo "==> Seeding database (RUN_SEED=true)"

    php artisan db:seed --force --no-interaction
fi

echo "==> Application initialization complete"

echo "==> Starting FrankenPHP on port ${PORT:-10000}"

exec "$@"
