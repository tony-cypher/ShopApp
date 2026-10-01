# Deployment Runbook — Render (API) + Vercel (Storefront)

Operational checklist for deploying this repo. The README (§8) explains the background;
this file is the step-by-step you follow on deploy day.

---

## 0. What was verified / fixed for Docker (2026-10-01)

- `backend/docker/start.sh` contained stray Markdown code fences → **the container entrypoint
  crashed instantly**. Rewritten; `sh -n` passes and the full boot sequence was simulated
  locally (config:cache → route:cache → view:cache → migrate → optional seed → exec server)
  **with no `.env` file** (env-vars only, production flags) — exactly the container condition.
- Added a fail-fast `APP_KEY` guard: if the env var is missing the container exits at boot
  with a clear log message instead of serving 500s.
- Verified `php artisan route:cache` / `view:cache` succeed on this Laravel version, and the
  cached app still serves `/up` and `/api/products` (Render's health check path).
- Verified no locked Composer package requires PHP > 8.4 (the FrankenPHP image is PHP 8.4).
- `.gitattributes` already forces LF for `*.sh` / `Dockerfile` / `Caddyfile` (CRLF would
  break the Linux container).
- `.dockerignore` now also excludes `backend/*.md`.

---

## 1. Prerequisites

- Repo pushed to GitHub (`main`).
- Supabase Postgres credentials (Project Settings → Database; prefer the **session pooler**
  host + port 5432 from Render, or direct `db.<ref>.supabase.co`).
- An `APP_KEY`. Generate locally with:

  ```bash
  cd backend && php artisan key:generate --show
  ```

---

## 2. Backend → Render (Docker)

1. Render dashboard → **New + → Blueprint** → pick the repo. Render reads `render.yaml`
   (service `mlc-api`, Docker runtime, health check `/up`).
2. Fill the `sync: false` env vars:

   | Key | Value |
   | --- | --- |
   | `APP_KEY` | output of the command above |
   | `APP_URL` | `https://<your-service>.onrender.com` |
   | `FRONTEND_URL` | your Vercel URL (set after step 3; update + redeploy later) |
   | `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` | from Supabase |
   | `CORS_ALLOWED_ORIGINS` | leave empty unless you add extra domains |

   Defaults already set by the blueprint: `DB_CONNECTION=pgsql`, `DB_PORT=5432`,
   `DB_SSLMODE=require`, `DB_PERSISTENT=true`, `LOG_CHANNEL=stderr`, `MAIL_MAILER=log`,
   `RUN_SEED=false`.
3. **First deploy**: build takes a few minutes (Composer install inside Docker). On boot the
   container caches config and runs `php artisan migrate --force` (retries 5×).
4. **Seed once**: set `RUN_SEED=true` → Manual Deploy → watch logs → set back to `false`.
5. Verify: `https://<service>.onrender.com/up` → `OK`; `/api/products` → JSON.

Free tier: sleeps after ~15 min idle (first request ~30–60 s); no shell — use env vars +
`RUN_SEED` instead of tinker.

---

## 3. Frontend → Vercel

1. vercel.com/new → import the same repo.
2. **Root Directory:** `frontend` · Framework preset: Vite (build `npm run build` → `dist`).
3. **Environment variable:** `VITE_API_URL = https://<your-service>.onrender.com/api`
   (must include the `/api` suffix — Vite bakes it in at build time, so any change needs a
   redeploy).
4. Deploy. `frontend/vercel.json` keeps SPA deep links working.

> Do **not** leave `VITE_API_URL` set-but-empty anywhere: the app falls back to `/api` only
> when the variable is *unset* (`frontend/src/lib/api.ts`).

---

## 4. Wire the two together (once both URLs exist)

| Where | Change |
| --- | --- |
| Render → `FRONTEND_URL` | set to the Vercel URL → Manual Deploy (drives email links + CORS) |
| Google Cloud Console | add `https://<service>.onrender.com/api/auth/google/callback`; set `GOOGLE_REDIRECT_URI` on Render to match |
| Mailgun | set `MAIL_MAILER=mailgun` + `MAILGUN_DOMAIN` / `MAILGUN_SECRET` / `MAILGUN_ENDPOINT` on Render when ready for real email |

---

## 5. Post-deploy checklist

- [ ] `/up` → `OK`, `/api/products` → JSON (Render URL)
- [ ] Storefront loads products (correct `VITE_API_URL`)
- [ ] Register / login works (CORS errors usually mean wrong `FRONTEND_URL`)
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `RUN_SEED=false`
- [ ] Test checkout places an order (email lands in Render logs while `MAIL_MAILER=log`)
- [ ] Google sign-in works (if configured)
