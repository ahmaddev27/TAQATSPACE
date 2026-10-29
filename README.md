# TAQAT.space

Multi-tenant coworking platform for Gaza — Arabic-first (RTL) + English. Today it is a marketplace for discovering, booking and running coworking spaces (bookings, subscriptions, invoices, POS/café, chat). It is now evolving into a **full coworking operations platform**: network-based attendance through MikroTik routers, complete owner finance, the GSG utilization-rate (UR) donor programme, and super-admin approvals — replacing the CoWorkSpaces system used by TAQAT GAZA.

## Status at a glance

| | |
|---|---|
| **Platform** | Phases 1–4 + platform expansion + realtime (Firebase) + POS/café & cashier — **built** |
| **Roadmap** | Toward the CoWorkSpaces SRS: **14 milestones (M0–M13), 164 tasks, ≈ 138 working days (≈ 27.6 weeks)** |
| **Current milestone** | **M0 — Readiness & discovery** ([milestone](https://github.com/ahmaddev27/TAQATSPACE/milestone/1)) |
| **Production** | 🟢 [taqat.space](https://taqat.space) + [api.taqat.space](https://api.taqat.space/api/health) — at PR #162 (2026-06-30). POS/cashier and later work are on `dev`, pending release R0 ([#186](https://github.com/ahmaddev27/TAQATSPACE/issues/186)) |
| **Staging** | 🟢 [staging.taqat.space](https://staging.taqat.space) — deploys from `dev` |
| **Default branch** | `dev` |
| **Board** | [TaqatSpace Roadmap](https://github.com/users/ahmaddev27/projects/1) |
| **API docs** | Scramble UI `/docs/api` + [`api-docs/openapi.json`](api-docs/openapi.json) + Postman collection (`composer api:docs`) |
| **Last updated** | 2026-10-29 |

---

## Roadmap

Planning documents (in [`DOCS/new plan/`](DOCS/new%20plan/)):

- [**Milestone Sheet**](DOCS/new%20plan/TaqatSpace-Milestone-Sheet.md): every task with its SRS reference, the current code state, effort, and a link to its GitHub issue. A tracking copy is in [`.xlsx`](DOCS/new%20plan/TaqatSpace-Milestone-Sheet.xlsx).
- [**Development Plan**](DOCS/new%20plan/TaqatSpace-Dev-Plan.md): gap matrix, target architecture, data model, multi-agent execution, GitHub workflow, test/release/cutover plans.
- Source requirements, both in Arabic: [SRS](DOCS/new%20plan/SRS%20%E2%80%94%20%D8%A7%D9%84%D9%88%D8%AB%D9%8A%D9%82%D8%A9%20%D8%A7%D9%84%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A%D8%A9.md) and the [original milestone sheet](DOCS/new%20plan/CoWorkSpaces-Milestone-Sheet.md).

| Milestone | Scope | Tasks | Days | Release |
|---|---|---|---|---|
| [M0](https://github.com/ahmaddev27/TAQATSPACE/milestone/1) | Readiness & discovery: ship POS, security fixes, hosting, source data | 11 | 6 | R0 |
| [M1](https://github.com/ahmaddev27/TAQATSPACE/milestone/2) | Foundation: tenancy, staff roles, 2FA, audit log, approvals, settings | 14 | 9.75 | R1 |
| [M2](https://github.com/ahmaddev27/TAQATSPACE/milestone/3) | MikroTik integration (router PUSH sync, installer, commands) | 14 | 14.5 | R1 |
| [M3](https://github.com/ahmaddev27/TAQATSPACE/milestone/4) | Data engine: sessions, attendance, stats, scores, alerts | 12 | 12.5 | R1 |
| [M4](https://github.com/ahmaddev27/TAQATSPACE/milestone/5) | Owner operations: dashboard, live monitor, members hub, contracts, bookings, stations | 17 | 16.25 | R2 |
| [M5](https://github.com/ahmaddev27/TAQATSPACE/milestone/6) | Finance v2: plans, invoices, receipts, credit notes, wallet, VAT, imports (no payment gateway) | 23 | 19 | R2 |
| [M6](https://github.com/ahmaddev27/TAQATSPACE/milestone/7) | GSG programme: UR engine, month close, UTR review, programs & claims | 14 | 14.75 | R3 |
| [M7](https://github.com/ahmaddev27/TAQATSPACE/milestone/8) | Analytics, 19 reports, exports, report builder | 5 | 7.5 | R3 |
| [M8](https://github.com/ahmaddev27/TAQATSPACE/milestone/9) | Super admin: UTR approvals, funded seats, claims, approvals queue, router monitoring | 10 | 7.5 | R3 |
| [M9](https://github.com/ahmaddev27/TAQATSPACE/milestone/10) | Freelancer portal: status, attendance, wallet, requests, devices | 10 | 6 | R4 |
| [M10](https://github.com/ahmaddev27/TAQATSPACE/milestone/11) | Public site `/s/{slug}`, site builder, hotspot guest self-registration | 8 | 5.5 | R4 |
| [M11](https://github.com/ahmaddev27/TAQATSPACE/milestone/12) | End-to-end flows across all panels | 10 | 5 | R5 |
| [M12](https://github.com/ahmaddev27/TAQATSPACE/milestone/13) | Hardening: security, performance, localization | 8 | 5.5 | R5 |
| [M13](https://github.com/ahmaddev27/TAQATSPACE/milestone/14) | TAQAT GAZA migration, UAT, cutover from CoWorkSpaces | 8 | 8.25 | R5 |

Key decisions:
- Build on top of the existing platform.
- Owner finance is complete but with **no payment gateway**.
- Sign-in stays Taqat SSO, linked to the MikroTik username (no OTP).
- Routers **push** data to `POST /api/v1/hotspot/{code}/sync`.
- Move to a dedicated server before the first real router.
- Run in parallel on the TAQAT GAZA router before the cutover.

---

## How we work

1. **One issue per task.** Each roadmap task is a GitHub issue whose body is its spec: goal, scope, technical design, acceptance criteria, tests and dependencies (linked).
2. **Start** with `scripts/task.sh start 2.4`. This creates a branch linked to the issue (e.g. `feat/m2-4`) and moves the card to **In Progress**.
3. **Implement** with the multi-agent cycle described in the development plan (§9). An architect writes the spec and API contract. Backend and frontend coders work in parallel worktrees. A reviewer checks the result, and it is verified against the acceptance criteria.
4. **Open the PR** with `scripts/task.sh pr 2.4`. This opens a PR to `dev` with `Closes #N` and moves the card to **In Review**. The [PR template](.github/pull_request_template.md) carries the definition of done.
5. **Merge** to close the issue and move the card to **Done**. Merging to `dev` deploys staging. A release PR `dev → main` deploys production.

Everything in git and on GitHub (commits, PRs, issues, docs) is written in English.

---

## Stack & repo

Laravel 13 (PHP 8.3 on the server, 8.4 locally) · Next.js 16 + React 19 (App Router, TypeScript) · MySQL 8 · AWS S3 (presigned URLs) · Sanctum + Spatie Permission · next-intl (ar default RTL / en) · Firebase (Firestore chat + FCM push) · mPDF (Arabic PDFs) · Scramble (OpenAPI) + Postman.

| Path | What |
|------|------|
| [`backend/`](backend/) | Laravel REST API |
| [`frontend/`](frontend/) | Next.js web app |
| [`DOCS/new plan/`](DOCS/new%20plan/) | Current roadmap: SRS, milestone sheet, development plan |
| [`DOCS/`](DOCS/) | Original plan, integration notes, presentation, design prototype, archive |
| [`api-docs/`](api-docs/) | Generated `openapi.json` + Postman collection |
| [`firebase/`](firebase/) | Firestore rules + indexes |
| [`scripts/`](scripts/) | `task.sh` (task workflow), `setup-github-deploy.sh` |
| [`.github/`](.github/) | CI + deploy workflows, PR and issue templates |

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
Health: `GET /api/health` · interactive API docs: `/docs/api` · regenerate docs + Postman: `composer api:docs`

### Frontend setup
```bash
cd frontend
cp .env.local.example .env.local   # NEXT_PUBLIC_API_URL=http://localhost:8000/api
npm install
npm run dev                        # http://localhost:3000 (/ar default, /en)
```

### Seeded accounts (after `migrate:fresh --seed`)
| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@taqat.space` | `password` |
| Owner / Freelancer | see seeder output | `password` |

### Tests
- Backend: `cd backend && php artisan test` (~607 PHPUnit feature tests, sqlite in-memory).
- Frontend: `cd frontend && npm run test` (Vitest) · `npm run lint` · `npm run e2e` (Playwright smoke).

### Conventions
- Layering: thin controllers → services → repositories for complex queries → models, wired with constructor DI.
- Requests and responses: FormRequests for validation, API Resources for output, one `ApiResponse` envelope.
- Data: enums for statuses. UUID primary keys, except high-volume network/attendance tables, which use BIGINT.
- Routes: modular files in `backend/routes/api/*.php`.
- Translations: i18n namespace files are wrapped in their own key.

---

## What's built today

### 🌐 Public
- **Landing page.** The landing, About, FAQ and Contact pages are driven by the admin CMS.
- **Explore.** Map (MapLibre + OpenFreeMap) with filters for city, price, rating and amenities, plus search.
- **Workspace detail.** Gallery, seat types and pricing (monthly/daily), amenities, hours, reviews and map, with a booking panel.
- **Sign-in.** Sign in with **Taqat SSO** (OIDC + PKCE). First login goes through onboarding (choose freelancer or owner). Staff use a separate admin login.

### 👤 Freelancer
- **Dashboard.** Subscription, seat, internet credentials card, and invoice alerts.
- **Booking.** Request a monthly or daily seat; the owner approves it.
- **Subscription.** View and cancel it, and review the workspace.
- **Invoices.** List and Arabic PDF, receipt upload, and partial payments.
- **Café ordering.** Order from the workspace café (POS).
- **Realtime chat** with attachments, notifications (in-app + web push), and profile.

### 🏢 Workspace owner
- **Dashboard.** KPIs and analytics.
- **Members, subscriptions and seats.** Members, subscriptions, the seat board (assign, unassign, rename), and booking requests. Approving a request creates the subscription and the seat assignment, and issues internet credentials.
- **Internet packages, resources and expenses.**
- **Invoices.** Issue, record full or partial payments, review receipts, send reminders, and download PDFs.
- **POS / café.** Terminal, products and inventory, sales reports, and invites for **cashier** staff.
- **Communication.** Broadcast (email/SMS), announcements, and realtime chat.
- **Reports.** Revenue, collections aging, monthly P&L, and package uptake, with CSV exports.
- **Settings.** Profile, location, seat types and pricing, amenities, photos, logo, hours, and messaging.

### 🧾 Cashier
- POS terminal with an order queue: new → preparing → ready → completed, plus refunds.
- Products and stock (with permission) and sales reports.

### 🛡️ Super admin
- **Dashboard.** Platform KPIs and tracked revenue (paid / outstanding).
- **Workspaces and users.** Approve, suspend and publish workspaces; manage users.
- **Admin accounts.** Seven permissions, enforced across the admin API and pages.
- **Billing tracking.** Subscriptions and invoices: mark paid and attach a receipt.
- **Reports and analytics.** By city and gender, with CSV exports.
- **CMS.** Landing page, site content, about, branding, and cities.
- **Messaging.** SMTP/SMS configuration, broadcast, usage, and the contact inbox. Chat with any user.

### 🔌 Integrations & background jobs
- **Academy partner API.** `X-Api-Key`, plus signed `booking.approved/rejected` webhooks.
- **Services.** SMS gateways (HotSMS, MTC), S3 media, and Firebase (Firestore + FCM).
- **Scheduler.** `invoices:generate-monthly`, `invoices:mark-overdue`, `invoices:remind-overdue`, `subscriptions:notify-expiring`, and a queue drain every minute. See [CRON_AND_NOTIFICATIONS.md](CRON_AND_NOTIFICATIONS.md).

> **Billing model:** no payment gateway — payments happen offline and are recorded in the platform. Platform billing is admin-tracked (mark paid + receipt). Full workspace-owner finance (plans, invoices with lines, receipts, credit notes, wallet, VAT) arrives in M5.

---

## Open operational items (M0)

| Item | Issue |
|---|---|
| Release `dev → main` (ships POS/cashier) | [#186](https://github.com/ahmaddev27/TAQATSPACE/issues/186) |
| Remove the unauthenticated `ops/clear-cache` route | [#187](https://github.com/ahmaddev27/TAQATSPACE/issues/187) |
| Rotate previously exposed secrets (DB, AWS, Firebase, SSO) | [#188](https://github.com/ahmaddev27/TAQATSPACE/issues/188) |
| Expire ended subscriptions (`subscriptions:expire`) | [#189](https://github.com/ahmaddev27/TAQATSPACE/issues/189) |
| Hosting for router sync (dedicated server with the current provider) | [#190](https://github.com/ahmaddev27/TAQATSPACE/issues/190) |
| Verify on the server: Firestore indexes deployed, `schedule:run` cron and queue worker running | — |

---

## Documentation

- [`DOCS/new plan/`](DOCS/new%20plan/) — current roadmap (SRS, milestone sheet, development plan).
- [DEPLOYMENT.md](DEPLOYMENT.md) — CI/CD, servers, paths and ports.
- [CRON_AND_NOTIFICATIONS.md](CRON_AND_NOTIFICATIONS.md) — scheduler, queue and notification setup.
- [`DOCS/milstones.md`](DOCS/milstones.md) — the original Phase 1–4 plan (complete).
- [`DOCS/mikrotik-integration-plan.md`](DOCS/mikrotik-integration-plan.md) — **superseded**: its pull design is replaced by the push protocol in the development plan §6.2.
- [`DOCS/integration/academy-work-integration.md`](DOCS/integration/academy-work-integration.md) — Academy ↔ Work partner integration.
- [`DOCS/presentation/`](DOCS/presentation/) — feature deck (as of 2026-06-09; predates POS).
- [`DOCS/archive/`](DOCS/archive/) — the previous README (full build log and changelog up to 2026-06-11) and the old dev-plan spreadsheet.

---

## Changelog

- **2026-10-29**
  - New roadmap adopted (PR #350): milestone sheet, development plan, 164 task issues with specs, 14 milestones, and the project board.
  - Default branch switched to `dev`.
  - This README was rewritten for the new phase.
- **2026-07-05 → 07-07** — POS/café + cashier (PRs #163–#185):
  - Cashier role with SSO invite.
  - Catalogue and stock ledger.
  - Orders, payments and refunds.
  - Freelancer café ordering.
  - Owner and cashier terminals.
  - Sales reports, with POS revenue included in the P&L.
- **2026-06-21 → 06-30**:
  - Dynamic cities.
  - Academy partner API and webhooks.
  - Daily seat booking.
  - Partial payments and the payment ledger.
  - Receipt review.
  - Owner reports (aging, P&L, package uptake).
  - Workspace logo and invoice prefixes.
  - Internet credentials (issued by email/SMS).
  - SMS gateways.
  - The test suite grown to ~607 tests.
- **Up to 2026-06-11**: see [`DOCS/archive/README-2026-06-11.md`](DOCS/archive/README-2026-06-11.md).
