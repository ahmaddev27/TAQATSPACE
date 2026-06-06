# TAQAT.space — Build Progress & Guide

Arabic-first (RTL) + English coworking-space marketplace for Gaza. Three roles: **Freelancer**, **Workspace Owner**, **Super Admin**.

> This README is the **live progress tracker**. It mirrors `DOCS/milstones.md` and is updated at the start (🔄) and end (✅) of every task.

## Dashboard

| | |
|---|---|
| **Overall** | 57 / 80 tasks (71%) — **Phases 1–3 complete** |
| **Current phase** | Phase 1–3 ✅ · **Phase 4 in progress** (M10 dashboard ✅ · M11 reports/exports ✅ → M12 Launch) |
| **Current milestone** | M01–M09 ✅ · M10 admin dashboard ✅ · M11 reports/exports ✅ |
| **Current sprint** | S1–S5 done |
| **Deployed** | 🟢 **Prod** [taqat.space](https://taqat.space) + [api.taqat.space](https://api.taqat.space/api/health) · 🟢 **Staging** [staging.taqat.space](https://staging.taqat.space) + api.staging.taqat.space |
| **API docs** | 🟢 Scramble UI `/docs/api` + [`api-docs/openapi.json`](api-docs/openapi.json) (**66 endpoints**) + Postman collection — regenerated |
| **Last updated** | 2026-06-04 |

**Legend:** ☐ Todo · 🔄 In Progress · ✅ Done · ⏸ Blocked · ⏭ Deferred

---

## Stack & repo

Laravel 13 (PHP 8.4) · Next.js 15 (App Router, TS) · MySQL 8 · AWS S3 · Sanctum + Spatie Permission · next-intl (ar default RTL / en) · Redis optional locally (Laragon) · API docs: Scramble (OpenAPI) + Postman.

| Path | What |
|------|------|
| [`backend/`](backend/) | Laravel 13 REST API |
| [`frontend/`](frontend/) | Next.js 15 web app |
| [`DOCS/`](DOCS/) | Spec (`milstones.md`) + design prototype (`Desing/`) |
| [`api-docs/`](api-docs/) | Generated `openapi.json` + Postman collection |

### Backend setup
```bash
cd backend
cp .env.example .env   # set DB_DATABASE=taqat_space, AWS_* keys
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve            # http://localhost:8000
php artisan queue:work       # queued mail/notifications (separate terminal)
```
Health: `GET /api/health` · Interactive API docs: `/docs/api` · Regenerate docs+Postman: `composer api:docs`

### Frontend setup
```bash
cd frontend
cp .env.local.example .env.local   # NEXT_PUBLIC_API_URL=http://localhost:8000/api
npm install
npm run dev                        # http://localhost:3000 (ar at /, en at /en)
```

### Seeded accounts (after `migrate:fresh --seed`)
| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@taqat.space` | `password` |
| Owner / Freelancer | see seeder output | `password` |

### Conventions
Thin Controllers → Services → Repositories · constructor DI · FormRequests for validation · API Resources for output · one JSON envelope (`{data,message}` / `{message,errors}`) · UUID primary keys · design tokens ported verbatim from `DOCS/Desing`.

---

## Milestones

| MS | Milestone | Phase | Sprint | Status | % |
|----|-----------|-------|--------|--------|---|
| M01 | Project Setup & Dev Environment | P1 | S1 | ✅ | 90% (CI deferred) |
| M02 | Authentication System (3 roles) | P1 | S2 | ✅ | 100% |
| M03 | Database Schema — Core Tables | P1 | S2 | ✅ | 100% |
| M04 | Public Marketing Site | P2 | S3 | ✅ | 100% |
| M05 | Workspace Owner Dashboard — Core | P2 | S3–S4 | ✅ | 100% |
| M06 | Freelancer Dashboard — Core | P2 | S4–S5 | ✅ | 100% |
| M07 | Invoicing System | P3 | S6 | ✅ | 100% |
| M08 | Messaging, Notifications & Real-time | P3 | S7 | ✅ | 95% (realtime needs Pusher creds) |
| M09 | Announcements, Packages & Reviews | P3 | S7 | ✅ | 100% |
| M10 | Super Admin Dashboard | P4 | S8 | ☐ | 0% |
| M11 | Reports, Exports & Analytics | P4 | S8 | ☐ | 0% |
| M12 | Testing, Hardening & Production Launch | P4 | S9 | ☐ | 0% |

---

## Phase 1 — Foundation & Infrastructure

### M01 — Project Setup & Dev Environment (S1)
- ✅ **T001** — Next.js 16 init (App Router, TS, Tailwind v4, next-intl RTL ar/en, fonts, ported design tokens) · FE · SP3 · _`/` ar-rtl, `/en` en-ltr verified_
- ✅ **T002** — Laravel 13 API-only init (CORS, Sanctum, .env) · BE · SP3 · _Sanctum/Spatie/Scramble, CORS, health, ApiResponse, role middleware; **S3 SDK deferred** (codeload throttled — local disk in dev, `composer require league/flysystem-aws-s3-v3` + `FILESYSTEM_DISK=s3` on server)_
- ⏭ **T003** — Docker Compose dev env · DevOps · SP5 · _Deferred — Laragon native per decision_
- ✅ **T004** — GitHub Actions CI (backend tests + frontend lint/tsc/build on PR) · DevOps · SP3 · _+ SSH deploy workflows (prod/staging) — see [DEPLOYMENT.md](DEPLOYMENT.md)_
- ✅ **T005** — Redis configuration (optional locally, env-gated) · DevOps · SP2 · _DB drivers default; `/api/health/redis` graceful (`unavailable`)_
- ✅ **M01-DOC** — API docs: Scramble (`/docs/api` + `openapi.json`, 10 endpoints) + `composer api:docs` → Postman collection

### M03 — Database Schema — Core Tables (S2) ✅
- ✅ **T006** — Users migration (UUID, role/status enums) + model · DB · SP3
- ✅ **T007** — Core business migrations (workspaces, seats, subscriptions, invoices) + models · DB · SP5
- ✅ **T008** — Supporting migrations (packages, bookings, messages, announcements, reviews, pivot) + models · DB · SP5
- ✅ **T009** — Seeders (Gaza data: 26 users, 5 workspaces, 67 seats, 50 subs, 150 invoices, 18 bookings, 75 msgs, 15 reviews) · DB · SP3

### M02 — Authentication System (S2)
- ✅ **T010** — Registration endpoint (3 roles, file upload) · BE · SP5 · `POST /api/auth/register` → 201 (verified)
- ✅ **T011** — Login, logout, token mgmt · BE · SP3 · `/login` 200, `/me`, `/logout` 204, wrong-pw 401
- ✅ **T012** — Email verification & password reset (queued, SPA URLs) · BE · SP3 · `/forgot-password` 200
- ✅ **T013** — Role-based gates & middleware · BE · SP3 · _EnsureFreelancer/Owner/Admin + aliases_
- ✅ **T014** — Next.js auth layer (middleware role-routing, AuthProvider, **httpOnly cookie bridge**, typed api) · FE · SP5 · _login→cookie + 307 redirects verified live_
- ✅ **T015** — Login page (bilingual, eye toggle, 401/403 states) · FE · SP3
- ✅ **T016** — Register as Freelancer (3-step Stepper, file upload) · FE · SP5
- ✅ **T017** — Register as Workspace (4-step + map stub + Pending Review) · FE · SP5 · _note: workspace detail fields persisted in Phase 2 (T023)_
- ✅ **T018** — Forgot & reset password pages (60s lockout, token from URL) · FE · SP2

---

## Phase 2 — Core Dashboards ✅
- **M04** ✅ T019 Home · T020 Explore (Leaflet+filters) · T021 Detail · T022 About/FAQ/Contact · T023 Workspaces API · T024 Search · T025 Photos · T026 Seats API
- **M05** ✅ T027 Owner dashboard · T028 Members · T029 Seat map · T030 Assignment · T031 Bookings · T032 Settings + T038/039 Packages
- **M06** ✅ T033 Freelancer home · T034 Subscription · T035 Profile · T036 Booking submit · T037 Booking approval
- _Built via multi-agent workflows (backend: 4 parallel · frontend: foundation + 3 parallel). 41 API endpoints; build+tsc+lint green; live smoke verified._

## Phase 3 — Invoicing & Comms ✅
- **M07** ✅ T040 monthly auto-gen (cron) · T041 model/status · T042 mark-paid+remind · T043 overdue cron · T044 Arabic PDF (DomPDF) · T045 owner invoices UI · T046 freelancer invoices · T047 alert cards
- **M08** ✅ T048 messages · T049 broadcast · T050/051 broadcast events (realtime wired, `log` driver — needs Pusher creds) · T052 notifications · T053 notification center (bell+polling) · T054 owner messaging UI
- **M09** ✅ T055/056 announcements · T057/058 reviews (done in P2)
- _58 API endpoints; backend 3 parallel agents + frontend 3 parallel agents; build+tsc+lint green; live-smoke 200._

## Phase 4 — Admin & Launch _(not started)_
**M10** ☐ T059–T068 · **M11** ☐ T069–T070 · **M12** ☐ T071–T080

---

## Changelog

- **2026-06-06 (cont.)** — **Phase 4 M11 + admin CRM + landing reorder (on `feat/phase-2`).** **M11 Reports/exports done**: backend `GET /admin/reports` (revenue-by-month, status breakdowns, top workspaces) + `GET /admin/exports/{type}` streamed CSV (UTF-8 BOM); frontend `/admin/reports` (recharts) + CSV "Export" buttons via an auth-proxying route handler. **Admin "CRM" section**: renamed/grouped the content hub (`/admin/rm` → `/admin/crm`) into a dedicated CRM nav group (Landing + Site/FAQ/About/How-it-works editors). **Landing CMS: section reordering + live preview** (Layout tab with up/down + enable toggles; sticky preview pane; public landing renders by `sections_order`). Fixes: featured-section subtitle now renders as its eyebrow; deploy "broken pipe" (subshell-detach Node). OpenAPI grew to ~80 paths.
- **2026-06-06** — **Phase 4 M10 started: Super-Admin dashboard + financial tracking (on `feat/phase-2`).** Business-model-aligned (no gateway, no plans — manual tracking): backend admin endpoints (stats, users list+status, subscriptions list, invoices list + mark-paid/unpaid + **receipt upload**, `receipt_path` migration, pdf/receipt URLs) — 8 routes, OpenAPI now 74 paths. Frontend: real admin dashboard (KPIs + tracked paid/outstanding revenue) + Workspaces / Users / Subscriptions / Invoices management pages (approve/suspend, mark paid w/ optional date, mark unpaid, upload receipt → also marks paid, download PDF / view receipt) + admin nav + i18n. Also fixed the deploy "broken pipe" (subshell-detach Node so SSH exits clean).
- **2026-06-04 (cont. 2)** — **rm CMS public display + registration fix + business model.** The admin **"rm" CMS now drives the public pages** (footer + Contact show admin-managed email/phone/whatsapp/address/social; FAQ items; About lead+sections; home How-it-works steps) — merging over i18n. **Workspace-owner registration fixed end-to-end**: was broken (never sent email/password, never created a workspace); now creates the owner (pending_verification) AND the Workspace (pending) with all details + per-seat-type pricing in one transaction. **Business model noted for Phase 4:** billing is **admin-managed status/payment tracking + receipt upload** — NO payment gateway, NO self-serve plans; pricing/seats are per-workspace (owner-set). OpenAPI/Postman regenerated.
- **2026-06-04 (cont.)** — **Pricing + content-control + UX wave (on `feat/phase-2`, awaiting merge to dev).** **Per-seat-type pricing — DONE end-to-end** (backend `seat_type_prices` + `PUT /workspace/seat-types`; owner "Seat types & pricing" tab; 3-type registration step; public SeatPricing + BookingPanel now data-driven; booking/subscription derive the type price). **Admin "rm" content CMS — backend + editor DONE, public display PENDING**: `site_settings`-backed `GET /content/{key}` + admin `GET/PUT /admin/content/{key}` for `site/faq/about/how_it_works`, plus the `/admin/rm` hub + bilingual editors — but FAQ/About/Contact/footer/home do **not yet read** the CMS (agent session dropped). Branded **route-transition loader** (TileLogo + animated bars). Fixes: admin Landing CMS showed raw i18n keys (admin.json namespace wrapping); auth-aware public header (Dashboard button when logged in). OpenAPI **66 paths** + Postman regenerated. **Still pending:** `rm` public-display integration; registration-scenario "real integration" after owner/freelancer signup.
- **2026-06-04** — **Big feature + polish wave (on `feat/phase-2`, awaiting merge).** Maps → **MapLibre + OpenFreeMap** (no token/account) with an interactive lat/lng picker (registration + owner settings). **S3 images via presigned URLs** (no public bucket). **Admin Landing CMS** (bilingual content editor → public landing merges over i18n; fixed the admin i18n namespace wrapping). **Invoice PDFs → mPDF** (correct Arabic shaping/RTL, on-brand redesign). **"Sign in with Taqat" SSO** (OIDC Authorization Code + PKCE, one-time-code session bridge; secrets via server env). Robust **logout** + confirmation. **Per-seat-type pricing** backend (`seat_type_prices` table, owner `PUT /workspace/seat-types`, booking/subscription derive from the type price; frontend UI pending). Localized **metadata for all pages** + branded SVG favicon. Borderless cards/sections, dark-mode button contrast, mobile hero/Why, adaptive detail gallery, auth-aware public header, demo-account login panel. Deploy fixes (node 24, broken-pipe stdin detach, CI on PR-only). OpenAPI + Postman regenerated (landing, seat-types, SSO included). _See git log on `feat/phase-2`._
- **2026-06-02** — Project kickoff. Plan approved (Laravel 13 · Next.js 15 · MySQL 8 · AWS S3 · Laragon native · Scramble docs). Toolchain verified (PHP 8.4.5, Composer 2.8.10, Laravel 13.12, Node 24.11, MySQL 8.4.3). Scaffolded backend + frontend. Backend: 11 enums, 10 UUID models, all migrations (users/spatie/morphs UUID-adapted + core + supporting tables), CORS, role middleware, health endpoints, ApiResponse, auth FormRequests + UserResource. README converted to live progress tracker.
- **2026-06-02 (cont.)** — **Backend Phase 1 complete & verified.** `migrate:fresh --seed` green; Gaza dataset seeded (factories + GazaData + DatabaseSeeder). Auth smoke-tested live: register 201, login 200, me 200, logout 204, forgot-password 200, 401/422 correct, health endpoints OK. Fixed: `package:discover` (skipped by earlier `--no-scripts`) registering Spatie/Scramble providers; `User` SPA notification overrides (queued verify/reset, frontend URLs). API docs live (`/docs/api`, `openapi.json` 10 paths) + Postman generated via `composer api:docs`. **Note:** AWS S3 SDK (`aws-sdk-php`) un-installable locally (codeload.github.com throttled ~839 B/s) → storage abstracted, local disk in dev, S3 enabled on server.
- **2026-06-02 (cont.)** — **Pushed to GitHub + CI/CD.** Initial commit pushed to `ahmaddev27/TAQATSPACE` (`main`). Created `dev` branch (staging). Added CI (PHPUnit + frontend lint/tsc/build on PRs/pushes) and **SSH deploy workflows**: merge→`main` deploys production (`taqat.space` + `api.taqat.space`), merge→`dev` deploys staging. Next.js `output:'standalone'` for Node hosting. Deploy keypair generated (gitignored). Full guide in [DEPLOYMENT.md](DEPLOYMENT.md). Workflows skip until secrets configured. _Pending user: SSH user/port, install public key on cPanel, set Secrets/Variables, verify Node.js support, create subdomains+docroots+.env._
- **2026-06-02 (cont.)** — **Phase 2 complete (M04+M05+M06) via multi-agent workflows.** Backend: 4 parallel agents → 21 endpoints (workspaces discovery/search/photos, seats/bookings/subscriptions, owner dashboard/members, freelancer dashboard/profile/packages/reviews); modular `routes/api/*.php` (zero conflicts); 45 routes load; smoke-tested live (public + owner + member 200, role guard 403); tests 4/4; OpenAPI 10→41 endpoints. Frontend: foundation agent (DashShell + 14 primitives + typed API client + i18n) then 3 parallel screen agents (public site, owner dashboard, freelancer dashboard); `next build`+`tsc`+`lint` green; live: public pages 200 (ar-rtl/en-ltr), owner dashboard renders real data, middleware redirects. On branch `feat/phase-2`.
- **2026-06-03 (cont.)** — **CI/docs fixes + release-gate flow.** (1) Fixed the `npm ci` deploy failure: local lockfile (npm 11/node 24) pins `@swc/helpers@0.5.15` but CI's npm 10 (node 22) resolved `next@16.2.7` to `0.5.23` → out-of-sync; aligned all three workflows to **node 24** (lockfile unchanged). (2) **API docs now live** on both envs — added the `viewApiDocs` gate (Scramble defaulted to local-only → 403); UI at `/docs/api`, spec at `/docs/api.json`, kill-switch `API_DOCS_ENABLED=false`. (3) Clarified deploy timing: deploy triggers on **merge** (`push`→dev/main), never on PR-open (that's CI-only). Documented the **approval gate** in [DEPLOYMENT.md](DEPLOYMENT.md) §3 — solo maintainer: protect `dev`/`main` with *require-PR + require-CI-checks* (approvals=0, since self-approval is disallowed). _Commits `4ccb903`, `7ec1f6a`._
- **2026-06-03** — **Staging + Production deployed and verified live (both envs green).** cPanel host has **no Passenger/"Setup Node.js App"** → frontend runs as a Next.js `standalone` Node process (staging `:3001`, prod `:3002`) behind a `mod_proxy` reverse-proxy `.htaccess` + cron keep-alive; backend served from Laravel `public/` per sub-domain. **Prod:** `https://taqat.space` → 307 `/ar` → 200 (Arabic RTL); `/ar/login` 200; end-to-end login (Next route handler → `api.taqat.space`) 200 with httpOnly `taqat_token`+`taqat_role` cookies; `api.taqat.space/api/health` 200. **Staging:** `staging.taqat.space` + `api.staging.taqat.space` 200. Fixes during bring-up: locked `composer` `platform.php=8.3.31` (server PHP 8.3 vs local 8.4) → downgraded symfony 8.1→7.4; cleared dev-only providers from `bootstrap/cache` (`Pail` 500); `localePrefix:"always"` (as-needed 404'd in standalone); stale-node fix via `fuser -k PORT/tcp` (not `pkill`); **apex reverse-proxy** scoped by `RewriteCond %{HTTP_HOST}` (api.* share the primary docroot) + `DirectoryIndex disabled` (so bare `/` proxies to Next instead of a stray `index.*`). [DEPLOYMENT.md](DEPLOYMENT.md) updated with concrete paths/ports. **Remaining for full automation:** set GitHub Variables `PROD_WEB_PATH`/`STAGING_WEB_PATH` (+ existing API path/url vars) and open PR `feat/phase-2 → dev` to exercise the pipeline. **Security:** rotate the DB password + AWS secret that were shared in chat.
- **2026-06-02 (cont.)** — **Frontend Phase 1 complete & verified → Phase 1 DONE.** Next.js 16 + React 19 + Tailwind v4 (CSS-first `@theme`) + next-intl (ar default RTL `/`, en `/en`). Design system ported verbatim from `DOCS/Desing` (tokens + 5 CSS files + typed `ui/` primitives + icons). Built: auth layer (`lib/api` server-fetch, route handlers, AuthProvider, middleware), 5 auth screens (login, freelancer/workspace register, forgot/reset). Gates green: `next build` ✓, `tsc --noEmit` ✓, `lint` ✓. **Live integration verified:** login via Next proxy → backend sets `taqat_token` (HttpOnly) + `taqat_role`; wrong-pw 401; unauth `/owner`,`/freelancer` → 307 `/login?redirect=`; `/` ar-rtl, `/en` en-ltr. Remaining Phase-1 item: T004 GitHub Actions CI (deferred — needs git repo/remote).
