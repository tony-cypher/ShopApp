#!/bin/sh
# Container start script (Render runs it on every deploy / restart).
set -eu

cd /app

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
  echo "   database not reachable yet — retrying in 5s…"
  sleep 5
done

# One-off convenience: set RUN_SEED=true in the Render dashboard for a single
# deploy to seed products/categories/demo user, then set it back to false.
if [ "${RUN_SEED:-false}" = "true" ]; then
  echo "==> Seeding database (RUN_SEED=true)"
  php artisan db:seed --force --no-interaction
fi

echo "==> Starting FrankenPHP on port ${PORT:-8080}"
exec frankenphp run --config /app/Caddyfile
