#!/bin/sh

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

```
attempt=$((attempt + 1))

echo "   database not reachable yet — retrying in 5s…"

sleep 5
```

done

if [ "${RUN_SEED:-false}" = "true" ]; then
echo "==> Seeding database (RUN_SEED=true)"

```
php artisan db:seed --force --no-interaction
```

fi

echo "==> Application initialization complete"

exec "$@"
