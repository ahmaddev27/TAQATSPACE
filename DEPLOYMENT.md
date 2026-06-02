# Deployment — TAQAT.space (cPanel + GitHub Actions)

Deploy is triggered automatically when a Pull Request is **merged** (i.e. pushed) to a branch:

| Branch | Environment | Frontend | Backend |
|--------|-------------|----------|---------|
| `main` | Production  | `taqat.space` | `api.taqat.space` |
| `dev`  | Staging     | `staging.taqat.space` | `api.staging.taqat.space` |

Workflows: [`.github/workflows/deploy-production.yml`](.github/workflows/deploy-production.yml), [`deploy-staging.yml`](.github/workflows/deploy-staging.yml). Each deploys **backend over SSH** (rsync code + `composer install --no-dev` built in CI, then `migrate --force` + cache) and **frontend over SSH** (Next.js `standalone` build, rsync, restart the Node app). CI (tests/lint/build) runs on every PR via [`ci.yml`](.github/workflows/ci.yml).

> The deploy jobs **skip safely** (a `preflight` job) until the secrets below are set — so merging now won't produce red runs.

---

## 1. GitHub configuration (one-time)

### Secrets — `Settings → Secrets and variables → Actions → Secrets`
| Name | Value |
|------|-------|
| `SSH_HOST` | `68.178.169.85` |
| `SSH_PORT` | cPanel SSH port (usually `22`; some hosts use `2222`/`21098`) |
| `SSH_USER` | **main cPanel account username** (NOT an FTP account like `live@…`) |
| `SSH_PRIVATE_KEY` | contents of `deploy/taqat_deploy_key` (generated locally; never committed) |

### Variables — same screen, **Variables** tab
| Name | Example value |
|------|---------------|
| `PROD_API_PATH` | `/home/<cpaneluser>/api.taqat.space` |
| `PROD_WEB_PATH` | `/home/<cpaneluser>/taqat.space` |
| `PROD_API_URL`  | `https://api.taqat.space/api` |
| `STAGING_API_PATH` | `/home/<cpaneluser>/api.staging.taqat.space` |
| `STAGING_WEB_PATH` | `/home/<cpaneluser>/staging.taqat.space` |
| `STAGING_API_URL`  | `https://api.staging.taqat.space/api` |

Get the exact paths from cPanel → **Domains** (the “Document Root” of each sub‑domain; the backend path is that folder’s parent, since Laravel’s docroot is `…/public`).

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

### Frontend (Next.js) per environment — needs Node.js
This project uses SSR + route handlers, so it **must** run as a Node app (it can’t be a static export).
1. cPanel → **Setup Node.js App** → Create:
   - Node version **≥ 20**, Application mode **Production**.
   - Application root = `taqat.space` (the `PROD_WEB_PATH`), Application URL = `taqat.space`.
   - **Application startup file = `server.js`** (Next’s standalone entry).
2. Deploy once (merge to `main`/`dev` or run the workflow manually) so the `standalone` bundle lands there, then **Restart** the app.
3. If your host has **no Node support**, host the frontend on **Vercel** instead (connect the repo, set `NEXT_PUBLIC_API_URL`, root dir `frontend/`) and keep only the backend on cPanel — tell me and I’ll switch the frontend workflow to Vercel.

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
