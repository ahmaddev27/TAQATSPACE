# Deployment — TAQAT.space (cPanel + GitHub Actions)

Deploy is triggered automatically when a Pull Request is **merged** (i.e. pushed) to a branch:

| Branch | Environment | Frontend | Backend |
|--------|-------------|----------|---------|
| `main` | Production  | `taqat.space` | `api.taqat.space` |
| `dev`  | Staging     | `staging.taqat.space` | `api.staging.taqat.space` |

Workflows: [`.github/workflows/deploy-production.yml`](.github/workflows/deploy-production.yml), [`deploy-staging.yml`](.github/workflows/deploy-staging.yml). Each deploys **backend over SSH** (rsync code + `composer install --no-dev` built in CI, then `migrate --force` + cache) and **frontend over SSH** (Next.js `standalone` build, rsync, restart the Node app). CI (tests/lint/build) runs on every PR via [`ci.yml`](.github/workflows/ci.yml).

> The deploy jobs **skip safely** (a `preflight` job) until the secrets below are set — so merging now won't produce red runs.

## ✅ Status — both environments are live
SSH shell access is enabled (server `68.178.169.85`, account `space`, home `/home/space`, port `22`) and both environments have been deployed and verified:

| Environment | Frontend | Backend |
|-------------|----------|---------|
| **Production** | `https://taqat.space` (→ `/ar`, Arabic RTL) | `https://api.taqat.space/api/health` → `200` |
| **Staging** | `https://staging.taqat.space` (→ `/ar`) | `https://api.staging.taqat.space/api/health` → `200` |

The one-time server setup below is **already done** for both. To finish wiring the GitHub Actions automation so future merges deploy automatically, set the secrets/variables in §1 — the concrete values are filled in.

> **Architecture note:** this host has **no Passenger / "Setup Node.js App" feature**. The frontend therefore runs as a plain **Next.js `standalone` Node process** (staging `:3001`, prod `:3002`) behind a **`mod_proxy` reverse-proxy `.htaccess`**, kept alive by a cron health-check. See §2 Frontend.

---

## 1. GitHub configuration (one-time)

### Secrets — `Settings → Secrets and variables → Actions → Secrets`
| Name | Value |
|------|-------|
| `SSH_HOST` | `68.178.169.85` |
| `SSH_PORT` | `22` |
| `SSH_USER` | `space` |
| `SSH_PRIVATE_KEY` | contents of `deploy/taqat_deploy_key` (generated locally; never committed) |

### Variables — same screen, **Variables** tab
| Name | Example value |
|------|---------------|
| `PROD_API_PATH` | `/home/space/public_html/api.taqat.space` |
| `PROD_API_URL`  | `https://api.taqat.space/api` |
| `STAGING_API_PATH` | `/home/space/public_html/api.staging.taqat.space` |
| `STAGING_API_URL`  | `https://api.staging.taqat.space/api` |
| `PROD_WEB_PATH` | `/home/space/nodeapps/prod-frontend` |
| `STAGING_WEB_PATH` | `/home/space/nodeapps/staging-frontend` |

> These are the **actual** paths used by the live deploys — set them verbatim so the first automated run targets the same directories (otherwise it would deploy to a new, unwired location).

**Important (backend):** in cPanel → **Domains**, set the Document Root of `api.taqat.space` to `…/api.taqat.space/public` and `api.staging.taqat.space` to `…/api.staging.taqat.space/public` (Laravel serves from `public/`). The `*_API_PATH` above is the app root (the parent of `public`).

Helper script (after `gh auth login`): [`scripts/setup-github-deploy.sh`](scripts/setup-github-deploy.sh).

### SSH key → cPanel
The public key is `deploy/taqat_deploy_key.pub`. In cPanel → **SSH Access → Manage SSH Keys → Import** (paste the public key) → **Authorize** it. Then put the **private** key (`deploy/taqat_deploy_key`) into the `SSH_PRIVATE_KEY` secret.

---

## 2. cPanel one-time setup

### Sub-domains & document roots
Create 4 sub-domains. For the **backend** ones, set the Document Root to the app’s `public` folder:
- `api.taqat.space` → docroot `…/api.taqat.space/public`
- `api.staging.taqat.space` → docroot `…/api.staging.taqat.space/public`
- `taqat.space`, `staging.taqat.space` → handled by the **Node app** (below), not a static docroot.

### Backend (Laravel) per environment
In each backend app folder (e.g. `~/api.taqat.space`):
1. Create the runtime dirs (not synced by CI): `mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache` and `chmod -R 775 storage bootstrap/cache`.
2. Create `.env` (copy `backend/.env.example`) and set: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.taqat.space`, `FRONTEND_URL=https://taqat.space`, real `DB_*` (create a MySQL DB + user in cPanel), `MAIL_*`, and the **AWS S3** keys.
3. Enable S3 on the server (where GitHub isn’t throttled): `composer require league/flysystem-aws-s3-v3` and set `FILESYSTEM_DISK=s3` (+ `AWS_*`). Locally we ship with the local disk.
4. First time only: `php artisan key:generate && php artisan migrate --force && php artisan storage:link`.
5. Queue + scheduler (cPanel → Cron Jobs):
   - `* * * * * cd ~/api.taqat.space && php artisan schedule:run >> /dev/null 2>&1`
   - A persistent worker (cPanel “Setup … / Cron”): `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` (the deploy runs `queue:restart` so workers pick up new code).

### Frontend (Next.js) per environment — standalone Node behind a reverse-proxy
This project uses SSR + route handlers, so it **must** run as a Node process (no static export). This host has **no Passenger / "Setup Node.js App"**, so each environment runs the Next.js `standalone` bundle directly and Apache reverse-proxies to it.

| Environment | App root (`*_WEB_PATH`) | Node port | Apex/site docroot for the proxy `.htaccess` |
|-------------|-------------------------|-----------|---------------------------------------------|
| Production | `/home/space/nodeapps/prod-frontend` | `3002` | `/home/space/public_html` (primary domain `taqat.space`) |
| Staging | `/home/space/nodeapps/staging-frontend` | `3001` | `…/staging.taqat.space` (sub-domain docroot) |

Per environment (done once; the deploy workflow only re-syncs the bundle and restarts Node afterwards):

1. **Land the bundle** — the deploy job rsyncs `frontend/.next/standalone/` to the app root (with `.next/static` and `public/` copied in).
2. **Start Node** (the deploy job also does this on every release):
   ```sh
   fuser -k 3002/tcp 2>/dev/null || true        # clear any stale listener by PORT (not pkill)
   cd /home/space/nodeapps/prod-frontend
   nohup env PORT=3002 HOSTNAME=127.0.0.1 NODE_ENV=production \
     NEXT_PUBLIC_API_URL=https://api.taqat.space/api node server.js > app.log 2>&1 &
   ```
3. **Reverse-proxy `.htaccess`** in the site docroot (requires `mod_proxy` + `mod_proxy_http`, both available here):
   ```apache
   DirectoryIndex disabled
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteCond %{HTTP_HOST} ^(www\.)?taqat\.space$ [NC]
     RewriteRule ^(.*)$ http://127.0.0.1:3002/$1 [P,L]
   </IfModule>
   ```
   - On the **primary domain**, the `api.*`/`staging.*` sub-domains live **under the same docroot** (`/home/space/public_html`), so the `RewriteCond %{HTTP_HOST}` scope is **mandatory** — without it the proxy would hijack `api.staging.taqat.space` (which has no own `.htaccess` to shield it). Keep the cPanel-generated PHP handler block at the top of the file.
   - `DirectoryIndex disabled` is **required** so a bare `/` is proxied as `/` (Next redirects it to `/ar`) instead of being remapped to a stray `index.*` placeholder that Node 404s. Retire any leftover `public_html/index.html` placeholder.
4. **Keep-alive cron** (cPanel → Cron Jobs) restarts Node if it dies:
   ```sh
   */2 * * * * /bin/sh -c 'curl -sf -m 5 -o /dev/null http://127.0.0.1:3002/en || (cd /home/space/nodeapps/prod-frontend && nohup env PORT=3002 HOSTNAME=127.0.0.1 NODE_ENV=production NEXT_PUBLIC_API_URL=https://api.taqat.space/api node server.js > app.log 2>&1 &)'
   ```
   (Staging is identical with port `3001`, the `staging-frontend` path, and `https://api.staging.taqat.space/api`.)

---

## 3. How a release flows
1. Branch off `dev`, open a PR → **CI** runs (tests + lint + build).
2. Merge to `dev` → **Deploy Staging** runs → verify on `staging.taqat.space`.
3. Open PR `dev → main`, merge → **Deploy Production** runs → live on `taqat.space`.
4. `workflow_dispatch` lets you re-deploy either environment manually from the Actions tab.

---

## 4. Security
- The FTP password shared earlier is **not used** by this pipeline (we deploy over SSH with a key). Still, **rotate it** in cPanel since it was exposed.
- Secrets live only in GitHub (encrypted) and the server `.env` — never in git.
- Restrict the deploy SSH key in cPanel if your host supports `command=`/source-IP limits.
