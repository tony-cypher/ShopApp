# MLC — Shop

A full-stack ecommerce demo: **React (Vite + TypeScript)** storefront with a **Laravel** API,
**Supabase Postgres** for storage and **Mailgun** for transactional email.

The UI covers every feature of the reference design — category pills, price-range slider with
histogram, star-rating filter, brand checklist, delivery options, sale badges, favourite hearts,
featured product card — plus a **test-only checkout**, order history, email confirmation and a
**light/dark theme**.

---

## 1. What's in the box

| Area | Details |
| --- | --- |
| Storefront | React 19, Vite, TypeScript, React Router |
| API | Laravel 13, Sanctum tokens, Eloquent |
| Database | Supabase Postgres (falls back to SQLite locally) |
| Email | Mailgun (falls back to the log driver in dev) |
| Checkout | Test only — Luhn-checked card, no processor, no charge |
| Auth | Register / login / email confirmation / favourites / orders |

### Features implemented from the reference image

- Header: logo, search, Orders, Favourites, Cart with live badge, account menu
- Category pills: **All Categories · Deals · Crypto · Fashion · Health & Wellness · Art · Home · Sport · Music · Gaming**
- Price Range card: average price, histogram, dual-thumb slider, Reset
- Star Rating filter ("4 Stars & up"), Brand checklist with marks + *More Brand*, Delivery Options (Standard / Pick Up)
- Product grid with **Top Item** badges, sale strikethrough prices, favourite hearts and a featured
  dark card with floating rating chips
- Extras: sorting toolbar, hover quick-add, product detail with size/colour options and reviews,
  cart, test checkout, order history, favourites page, dark mode

---

## 2. Requirements

- PHP **8.3+** with `pdo_pgsql`, `mbstring`, `openssl`, `curl`
- Composer 2
- Node **20+** and npm

---

## 3. Quick start (SQLite, no keys needed)

```bash
# API
cd backend
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://127.0.0.1:8000

# Storefront (second terminal)
cd frontend
npm install
npm run dev                   # http://localhost:5173
```

Vite proxies `/api/*` to `http://127.0.0.1:8000`, so no CORS setup is needed in development.

**Demo account:** `demo@mlc.test` / `password123`
**Test card:** `4242 4242 4242 4242`, any future expiry, any CVC.

---

## 4. Connecting Supabase Postgres

1. Open your project on [supabase.com](https://supabase.com) → **Project Settings → Database**.
2. Under **Connection string**, choose **URI** (direct connection) and copy the values.
   It looks like:
   `postgresql://postgres:YOUR-PASSWORD@db.abcdefghijklm.supabase.co:5432/postgres`
3. Put them in `backend/.env` (uncomment the Supabase block):

```env
DB_CONNECTION=pgsql
DB_HOST=db.abcdefghijklm.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=YOUR-DATABASE-PASSWORD
DB_SSLMODE=require
```

> **Where do I find each value?**
> - `DB_HOST` — the `db.<project-ref>.supabase.co` part of the URI
> - `DB_PASSWORD` — the database password you chose when creating the project
>   (reset it under *Project Settings → Database → Reset database password*)
> - `DB_SSLMODE=require` — Supabase requires TLS; Laravel passes this straight to the driver
> - Using the **connection pooler** instead? Port `6543`, user `postgres.<project-ref>`

4. Create the schema and seed the catalogue:

```bash
cd backend
php artisan migrate --seed
```

Re-running `--seed` is safe: every seeder uses `updateOrCreate`.

5. Verify:

```bash
php artisan tinker --execute="echo App\Models\Product::count().' products';"
```

---

## 5. Connecting Mailgun

Two emails are sent by the app:

| Mail | Trigger |
| --- | --- |
| `VerifyEmailMail` | Registration (and "Resend email" in the header banner) |
| `OrderConfirmedMail` | Successful test checkout |

1. In [Mailgun](https://app.mailgun.com) go to **Sending → Domain names** and add your domain
   (or use the free sandbox domain `sandboxXXXX.mailgun.org`).
2. Add the DNS records Mailgun shows, then wait for **Verified**.
3. Copy the **API key** from **Settings → API security** (starts with `key-`).
4. Fill in `backend/.env`:

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=key-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
MAILGUN_ENDPOINT=default          # use api.eu.mailgun.net for EU accounts

MAIL_FROM_ADDRESS="no-reply@mg.yourdomain.com"   # must be on the verified domain
MAIL_FROM_NAME="MLC"
FRONTEND_URL=http://localhost:5173               # used in the confirmation link
```

5. Confirm the wiring:

```bash
cd backend
php artisan tinker --execute="Mail::raw('hello', fn(\$m) => \$m->to('you@example.com')->subject('MLC test'));"
```

> **Sandbox domains** only deliver to addresses you add under *Authorized recipients*.
> Until Mailgun is configured, leave `MAIL_MAILER=log` — every email is written to
> `backend/storage/logs/laravel.log` instead, so registration and checkout still work end to end.

---

## 6. Sign in with Google (Google Cloud Console)

The login and register pages show a **Continue with Google** button. It stays disabled until
you create an OAuth client and paste the credentials into `backend/.env`.

### Create the OAuth client

1. Open <https://console.cloud.google.com> and sign in with your Google account.
2. Create (or select) a project — e.g. **MLC Shop**.
3. **APIs & Services → OAuth consent screen**
   - User type: **External** → **Create**.
   - Fill in an app name and your email; the rest can stay empty.
   - Under **Test users**, add the Gmail address you will sign in with (required while the
     consent screen is in *Testing* mode).
4. **APIs & Services → Credentials → Create credentials → OAuth client ID**
   - Application type: **Web application**.
   - **Authorized redirect URI**: `http://localhost:8000/api/auth/google/callback`
   - Create, then copy the **Client ID** and **Client secret**.

### Enable it in the app

5. In `backend/.env`, find the commented `GOOGLE_*` block (after the Mailgun block) and fill it in:

```env
GOOGLE_CLIENT_ID=1234567890-xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

6. The Laravel dev server picks the new values up automatically (restart `php artisan serve`
   if it was running). Reload <http://localhost:5173/login> — the Google button is now active,
   and `GET /api/auth/google/config` returns `{"data":{"enabled":true}}`.

> **How it works:** `/api/auth/google/redirect` sends the user to Google via Socialite; the
> callback finds or creates the user by email (stamping `google_id` and `avatar_url`), issues a
> Sanctum token and redirects to `/auth/google/callback#token=…` on the storefront. Signing in
> with Google also marks the address as verified, so no confirmation email is sent.

---

## 7. Project structure

```
backend/                  Laravel API
├─ app/Http/Controllers/Api
│  ├─ AuthController.php       register / login / google OAuth / verify-email / resend
│  ├─ CatalogController.php    products (filters, search, sort), categories, brands
│  ├─ CheckoutController.php   test checkout (Luhn check, stock, order + email)
│  ├─ FavoriteController.php   favourites for signed-in users
│  └─ OrderController.php      order history
├─ app/Mail                  VerifyEmailMail, OrderConfirmedMail
├─ scripts                   fetch-product-images.mjs (one-time image downloader)
├─ database/migrations       categories, brands, products, reviews, favorites, orders…
├─ database/seeders          33 products, 10 brands, 8 categories, reviews, demo user
├─ Dockerfile / Caddyfile    production container (Render)
├─ docker/start.sh           container boot: cache + migrate + serve
└─ routes/api.php

frontend/                 React storefront
├─ src/components          Header, CategoryPills, Sidebar, ProductCard, Stars…
├─ src/context             Theme, Auth, Cart, Favorites, Toast
├─ src/pages               Shop, Product, Cart, Checkout, Orders, Favourites, Login, Register, Verify
├─ vercel.json             SPA rewrites + cache headers
└─ src/index.css           design tokens (light + dark) and all styling
```

Repo root: `render.yaml` (Render blueprint), `.gitignore`, `README.md`.

### API endpoints

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/products` | `search, category, brands[], min_price, max_price, min_rating, delivery, deals, sort, per_page, page` |
| GET | `/api/products/{slug}` | product + reviews + related |
| GET | `/api/categories`, `/api/brands` | filter data |
| POST | `/api/auth/register` | sends the confirmation email |
| POST | `/api/auth/login` | returns a Sanctum token |
| GET | `/api/auth/google/config` | `{ enabled }` — drives the Google button state |
| GET | `/api/auth/google/redirect` | starts the Google OAuth flow |
| GET | `/api/auth/google/callback` | Google redirect target; hands the token back to the SPA |
| POST | `/api/auth/verify-email` | `{ token }` |
| POST | `/api/auth/resend-verification` | authenticated, throttled |
| POST | `/api/checkout` | test order + confirmation email |
| GET | `/api/orders` | authenticated |
| GET/POST/DELETE | `/api/favorites` | authenticated |

### Product images

Products use real photos, self-hosted in `frontend/public/img/products/` (one ~10–85 KB JPEG per
product, ~1.2 MB total). They are served same-origin by Vite/the static build and lazy-loaded, so
the catalogue stays fast with no external CDN calls at runtime.

- Seeding assigns `/img/products/<slug>.jpg` automatically (`ProductSeeder`, `Product` model).
- The one-time downloader script fetches freely-licensed photos from the
  [Openverse API](https://api.openverse.org): `node backend/scripts/fetch-product-images.mjs`
  (idempotent, respects the anonymous rate limit).
- `image_url` is snapshotted onto `order_items` at checkout, so order history and the
  confirmation email keep showing the photo even if a product changes later.
- Emoji remain as a fallback anywhere `image_url` is missing (e.g. cart items saved before
  this change).

---

## 8. Deploying to production (Render + Vercel)

| Piece | Host | Notes |
| --- | --- | --- |
| `frontend/` — React storefront | **Vercel** | static build (`npm run build` → `dist`) |
| `backend/` — Laravel API | **Render** (Docker) | PHP 8.4 via FrankenPHP, talks to Supabase Postgres |
| Database | **Supabase** | already live — no new DB needed |
| Email | **Mailgun** | or `MAIL_MAILER=log` to test in Render logs |

All deployment files are already in the repo: `render.yaml` (blueprint),
`backend/Dockerfile` + `backend/Caddyfile` + `backend/docker/start.sh`,
`frontend/vercel.json`, `backend/.env.production.example`, `frontend/.env.example`.

### 0. Push the repo to GitHub

The repo is already initialised (`.gitignore` keeps out `.env`, `vendor/`, `node_modules/`,
`dist/`, `database.sqlite`, logs). Commit and push:

```bash
git add -A
git commit -m "MLC shop — full-stack app"
git remote add origin https://github.com/<you>/mlc-shop.git
git push -u origin main
```

### 1. Backend → Render

1. [render.com](https://dashboard.render.com) → **New + → Blueprint** → connect the GitHub repo.
   Render reads `render.yaml` and creates the **mlc-api** web service (Docker, free plan,
   health check `/up`).
2. Render asks for the `sync: false` values (or set them under **Environment → Environment
   Variables** after the first deploy). Copy from `backend/.env.production.example`:

   | Key | Value |
   | --- | --- |
   | `APP_KEY` | run `php artisan key:generate --show` inside `backend/` locally, paste the output |
   | `APP_URL` | `https://mlc-api.onrender.com` (Render shows the service URL) |
   | `FRONTEND_URL` | your Vercel URL, e.g. `https://mlc-shop.vercel.app` |
   | `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` | from Supabase (**Connect → Session pooler** is the safe choice from Render; direct `db.<ref>.supabase.co` can fail on IPv6) |
   | `CORS_ALLOWED_ORIGINS` | leave empty unless you add extra domains |
3. **Create Web Service.** First build takes a few minutes (Composer install inside Docker).
   On boot the container caches config/routes, runs `php artisan migrate --force` (retries 5×)
   and serves on Render's `$PORT`.
4. Seed the catalogue once: set **`RUN_SEED=true`** → **Manual Deploy → Deploy latest commit**
   → watch the logs for the seed line → set `RUN_SEED=false` again.
5. Verify: open `https://mlc-api.onrender.com/up` → “OK”, and `/api/products` → JSON.

> **Free tier notes:** the service sleeps after ~15 min idle (first request after that takes
> ~30–60 s) and has no shell — use `RUN_SEED` and env vars instead of `php artisan tinker`.
> Deploy logs live in the **Events**/**Logs** tabs.

### 2. Frontend → Vercel

1. [vercel.com/new](https://vercel.com/new) → **Import** the same GitHub repo.
2. Configure the project:
   - **Root Directory:** `frontend`
   - **Framework Preset:** Vite (auto-detected) — build `npm run build`, output `dist`
   - **Environment Variables:** `VITE_API_URL` = `https://mlc-api.onrender.com/api`
     (your Render URL **+ `/api` suffix**)
3. **Deploy.** Vercel runs `npm ci && npm run build` and serves the SPA — the `vercel.json`
   rewrite keeps deep links like `/product/…` working.

CLI alternative: `cd frontend && npm i -g vercel && vercel --prod`.

### 3. Wire the two together (do this once URLs exist)

| Where | Change |
| --- | --- |
| Render → `FRONTEND_URL` | set to the Vercel URL → **Manual Deploy** (drives email links + CORS + Google callback) |
| Vercel → `VITE_API_URL` | already set in step 2 → every change here needs a **redeploy** (Vite bakes it at build time) |
| Google Cloud Console | add `https://mlc-api.onrender.com/api/auth/google/callback` to the OAuth client's **Authorized redirect URIs**, and set `GOOGLE_REDIRECT_URI` on Render to match |
| Mailgun | set `MAIL_MAILER=mailgun` + the three `MAILGUN_*` values on Render when ready to send real email |

Order of operations for a clean first rollout: **push → Render deploy → get API URL → Vercel
deploy with `VITE_API_URL` → get Vercel URL → back to Render, set `FRONTEND_URL` (+ Google
redirect URI) → redeploy Render.**

### Production checklist

- [ ] `/up` returns “OK” and `/api/products` returns JSON from the Render URL
- [ ] Storefront loads and the catalogue shows products (VITE_API_URL correct)
- [ ] Register/login works (check Render Logs if requests fail — usually CORS = wrong `FRONTEND_URL`)
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `RUN_SEED=false`
- [ ] Test checkout places an order and (with Mailgun) the confirmation email arrives
- [ ] Google sign-in works in production

---

## 9. Notes & troubleshooting

- **Dark mode** follows the OS on first visit and is remembered in `localStorage`.
- **Checkout is simulated.** Card numbers are validated with the Luhn algorithm and a test card is
  required, but nothing is ever charged and no card data leaves the app.
- **`php artisan migrate` fails with "no pg_hba.conf entry"** → check `DB_SSLMODE=require` and the
  exact password; special characters must be URL-encoded if you use `DB_URL`.
- **Frontend can't reach the API** → make sure `php artisan serve` is running on port 8000; the
  Vite proxy target lives in `frontend/vite.config.ts`.
- **Port already in use** → `php artisan serve --port=8001` and update the proxy target.
