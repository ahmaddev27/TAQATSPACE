# TAQAT.space — Development Reference

> **Stack:** Next.js 14 (App Router, TypeScript) · Laravel 11 (API-only) · PostgreSQL · Redis · REST API  
> **Roles:** Freelancer · Workspace Owner · Super Admin  
> **Language:** Arabic (RTL, primary) + English (LTR, full second locale)  
> **Structure:** 4 Phases · 12 Milestones · 9 Sprints · 80 Tasks

---

## Table of Contents

- [Phase 1 — Foundation & Infrastructure](#phase-1--foundation--infrastructure)
  - [Milestone M01 — Project Setup & Dev Environment](#milestone-m01--project-setup--dev-environment)
  - [Milestone M02 — Authentication System](#milestone-m02--authentication-system)
  - [Milestone M03 — Database Schema](#milestone-m03--database-schema)
- [Phase 2 — Core Dashboards](#phase-2--core-dashboards)
  - [Milestone M04 — Public Marketing Site](#milestone-m04--public-marketing-site)
  - [Milestone M05 — Workspace Owner Dashboard Core](#milestone-m05--workspace-owner-dashboard-core)
  - [Milestone M06 — Freelancer Dashboard Core](#milestone-m06--freelancer-dashboard-core)
- [Phase 3 — Invoicing, Messaging & Communications](#phase-3--invoicing-messaging--communications)
  - [Milestone M07 — Invoicing System](#milestone-m07--invoicing-system)
  - [Milestone M08 — Messaging, Notifications & Real-time](#milestone-m08--messaging-notifications--real-time)
  - [Milestone M09 — Announcements, Packages & Reviews](#milestone-m09--announcements-packages--reviews)
- [Phase 4 — Admin, Reports & Launch](#phase-4--admin-reports--launch)
  - [Milestone M10 — Super Admin Dashboard](#milestone-m10--super-admin-dashboard)
  - [Milestone M11 — Reports, Exports & Analytics](#milestone-m11--reports-exports--analytics)
  - [Milestone M12 — Testing, Hardening & Production Launch](#milestone-m12--testing-hardening--production-launch)
- [API Contract Index](#api-contract-index)

---

## Conventions Used in This Document

| Symbol | Meaning |
|--------|---------|
| `BE` | Laravel 11 backend task |
| `FE` | Next.js 14 frontend task |
| `API` | API contract definition |
| `DB` | Database migration / schema |
| `DevOps` | Infrastructure / CI / deployment |
| `Testing` | Automated tests |
| `P1–P4` | Priority: P1 = critical, P4 = low |
| `SP` | Story points (complexity proxy) |

---

---

# Phase 1 — Foundation & Infrastructure

> Establishes the technical skeleton: both applications boot, communicate, and enforce authentication across all three user roles. Nothing in Phase 2 should start until every milestone here is fully signed off.

---

## Milestone M01 — Project Setup & Dev Environment

**Goal:** Both apps run locally via Docker. CI pipeline is green on push. Any developer can onboard from a fresh machine in under ten minutes using the README.

**Success criteria:** `docker compose up` boots all services; `npm run dev` serves the Next.js app; `php artisan serve` (or Nginx proxy) serves the Laravel API; GitHub Actions runs lint + test on every pull request.

---

### Sprint 1 — Tasks T001–T005

---

#### T001 — Next.js 14 Project Init
**Type:** FE · **Priority:** P1 · **SP:** 3

**What to build:**  
Bootstrap the Next.js 14 application with the App Router, TypeScript in strict mode, and all global configuration needed to support Arabic-first RTL layout alongside English LTR.

**Frontend scope:**
- Run `npx create-next-app@latest` with `--typescript --tailwind --app --src-dir --import-alias "@/*"` flags.
- Configure `next.config.ts`: enable `i18n` via `next-intl`, set `output: 'standalone'` for Docker compatibility.
- Install and configure `next-intl` for bilingual support. Default locale: `ar`, secondary locale: `en`. Messages live in `/messages/ar.json` and `/messages/en.json`.
- Set `dir="rtl"` on `<html>` for Arabic; `dir="ltr"` for English. Do this dynamically in the root `layout.tsx` based on the active locale.
- Configure Tailwind: add `rtl` and `ltr` variant utilities. Import `IBM Plex Sans Arabic` (Arabic UI) and `Inter` (Latin + numerals) via `next/font/google`. Set `font-feature-settings: "tnum"` on tabular number elements.
- Establish folder structure:
  ```
  app/
    (public)/          # Marketing pages — no auth
    (auth)/            # Login, register, reset
    (dashboard)/
      owner/           # Workspace owner protected routes
      freelancer/      # Freelancer protected routes
      admin/           # Super admin protected routes
  components/
    ui/                # Primitive components (Button, Input, Badge, etc.)
    layout/            # Shell, Sidebar, TopNav
    features/          # Feature-specific composites
  lib/
    api.ts             # Typed API client (fetch wrapper)
    auth.ts            # Token utilities
    pusher.ts          # WebSocket client
  messages/
    ar.json
    en.json
  ```
- Add `.env.local.example` documenting all required env vars: `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_PUSHER_APP_KEY`, `NEXT_PUBLIC_MAP_TILE_URL`.

**Dependencies:** None.  
**Key risk:** Tailwind RTL utilities require `tailwindcss-rtl` plugin or Tailwind v3.3+ built-in `rtl:` prefix — confirm which approach is used and document it. Do not mix both.

---

#### T002 — Laravel 11 API Project Init
**Type:** BE · **Priority:** P1 · **SP:** 3

**What to build:**  
Bootstrap Laravel 11 configured as an API-only application. No Blade views except for the invoice PDF template (added in Phase 3). Sanctum installed and ready for token auth.

**Backend scope:**
- Run `laravel new taqat-api --git`.
- Delete or disable the `web.php` routes file. All routing lives in `routes/api.php`.
- Install: `laravel/sanctum`, `spatie/laravel-permission`, `fruitcake/laravel-cors` (or configure built-in CORS middleware in `config/cors.php`).
- Configure CORS: allow origins `http://localhost:3000` (dev) and `https://taqat.space` (prod). Allow `Authorization` and `Content-Type` headers. Credentials: `true`.
- Set `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` in `.env`.
- Configure `config/sanctum.php`: set `stateful` domains. Auth guard for API routes: `auth:sanctum`.
- Add a `GET /api/health` endpoint returning `{ status: "ok", timestamp }` — used by uptime monitoring.
- Add `.env.example` with all required keys documented: `DB_*`, `REDIS_*`, `MAIL_*`, `AWS_*`, `PUSHER_*`, `QUEUE_CONNECTION`.
- Set PHP 8.3 `strict_types=1` in all new files. Use typed properties and enums throughout.

**API endpoint:** `GET /api/health`  
**Dependencies:** None.  
**Key risk:** Laravel 11 removed the `Http` kernel bootstrapping — use the new `bootstrap/app.php` middleware registration pattern. Do not copy Laravel 10 patterns.

---

#### T003 — Docker Compose Dev Environment
**Type:** DevOps · **Priority:** P1 · **SP:** 5

**What to build:**  
A single `docker-compose.yml` at the monorepo root that starts all services needed for local development. Both apps connect to the same Postgres and Redis instances.

**Services to define:**
- `postgres`: `postgres:15-alpine`. Volume-mounted for data persistence. Health check on `pg_isready`.
- `redis`: `redis:7-alpine`. Expose port 6379.
- `nginx`: Routes `localhost:80/api/*` to the Laravel container, everything else to Next.js. Provides a single host for CORS simplicity in dev.
- `laravel`: PHP 8.3 FPM image. Mounts the `./backend` directory. Runs `php-fpm`.
- `nextjs`: Node 20 Alpine image. Mounts `./frontend`. Runs `npm run dev`.
- `queue-worker`: Same Laravel image, runs `php artisan queue:listen --timeout=60`.
- `scheduler`: Same Laravel image, runs a `while true; do php artisan schedule:run; sleep 60; done` loop.

**Healthchecks:** Postgres, Redis, and Nginx must be healthy before Laravel starts. Use `depends_on` with `condition: service_healthy`.

**`.env` strategy:** Both the Laravel and Next.js containers read from a single root `.env` file via `env_file:` in Docker Compose. Document which variables are consumed by each service.

**Dependencies:** T001, T002.  
**Key risk:** Nginx config for proxying `/api/` to PHP-FPM requires `fastcgi_pass` vs `proxy_pass` distinction. Use `proxy_pass` to the `laravel` container on port 9000 only if using FPM. Use `proxy_pass http://laravel:8000` if running Laravel with `artisan serve` inside the container.

---

#### T004 — GitHub Actions CI Pipeline
**Type:** DevOps · **Priority:** P1 · **SP:** 3

**What to build:**  
Two GitHub Actions workflows — one for each app — that run on every pull request targeting `main` and `develop`.

**Backend workflow (`.github/workflows/backend.yml`):**
- Trigger: `push` and `pull_request` on `main`, `develop`.
- Services: `postgres:15` and `redis:7` as job services.
- Steps: checkout → PHP 8.3 setup → `composer install` → copy `.env.testing` → `php artisan key:generate` → `php artisan migrate --env=testing` → `php artisan test --parallel`.
- Fail if any test fails or `phpstan` (level 6) reports errors.

**Frontend workflow (`.github/workflows/frontend.yml`):**
- Steps: checkout → Node 20 setup → `npm ci` → `npm run lint` (ESLint) → `npx tsc --noEmit` → `npm test` (Vitest).
- Fail on type errors, lint errors, or failing tests.

**Deployment workflow (`.github/workflows/deploy.yml`):**
- Trigger: manual `workflow_dispatch` + push to `main`.
- Uses SSH to connect to the DigitalOcean Droplet, pulls latest, runs migrations, restarts queue workers. (Set up in Sprint 9.)

**Dependencies:** T001, T002.

---

#### T005 — Redis Configuration
**Type:** DevOps · **Priority:** P1 · **SP:** 2

**What to build:**  
Configure Laravel to use Redis for three distinct purposes: cache, queue, and sessions — using separate Redis databases to prevent key collisions.

**Backend scope:**
- In `.env`: `CACHE_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`.
- In `config/database.php`, define three Redis connections:
  - `cache`: database 0
  - `queue`: database 1
  - `session`: database 2
- In `config/cache.php`: default store = `redis`, using the `cache` connection.
- In `config/queue.php`: default connection = `redis`, using the `queue` connection. Set `retry_after: 90`, `block_for: null`.
- In `config/session.php`: driver = `redis`, connection = `session`. `http_only: true`, `secure: true` in prod.
- Add a `GET /api/health/redis` endpoint that calls `Redis::ping()` and returns status — used in smoke tests.

**Dependencies:** T003.

---

## Milestone M02 — Authentication System

**Goal:** All three roles (Freelancer, Workspace Owner, Super Admin) can register, log in, verify their email, reset their password, and access only their own protected routes.

**Success criteria:** JWT issued on login; role-gated middleware blocks unauthorized access with `403`; password reset email delivers via Mailgun queue; all auth forms work in Arabic and English.

---

### Sprint 2 — Tasks T006–T018

---

## Milestone M03 — Database Schema

**Goal:** All 10 database tables exist, are properly related, and are seeded with enough realistic Gaza-context test data to develop against without needing production data.

**Success criteria:** `php artisan migrate:fresh --seed` completes without errors; all foreign keys enforce correctly; seeders produce at least 5 workspaces, 20 freelancers, 50+ subscriptions, and 3 months of invoice history.

---

#### T006 — Users Table Migration
**Type:** DB · **Priority:** P1 · **SP:** 3

**What to build:**  
The central `users` table used by all three roles. Role is stored as a PHP enum, not a string, for type safety.

**Backend scope:**
- Create migration `create_users_table` with columns:
  ```
  id (uuid — use UUIDs not auto-increment for all IDs in this project)
  name (string, 255)
  email (string, unique)
  email_verified_at (timestamp, nullable)
  password (string — bcrypt)
  phone (string, nullable)
  role (enum: 'freelancer', 'workspace_owner', 'admin')
  status (enum: 'active', 'suspended', 'pending_verification') default 'pending_verification'
  avatar (string, nullable — stores S3/R2 path)
  specialty (string, nullable — for freelancers: "Graphic Designer", "Developer", etc.)
  remember_token (string, nullable)
  created_at, updated_at
  ```
- Create a PHP 8.1+ `enum UserRole: string` and `enum UserStatus: string` in `app/Enums/`.
- Create `User` model with `$fillable`, `$hidden` (password, remember_token), `$casts` (role → UserRole enum, status → UserStatus enum).
- Install `spatie/laravel-permission` and run its migration. Assign Spatie roles after user creation to allow permission checks: `$user->assignRole($user->role->value)`.

**Dependencies:** T003.

---

#### T007 — Core Business Table Migrations
**Type:** DB · **Priority:** P1 · **SP:** 5

**What to build:**  
Four migrations for the primary business entities: workspaces, seats, subscriptions, and invoices.

**Backend scope:**

**`workspaces` table:**
```
id (uuid)
owner_id (uuid, FK → users.id, onDelete cascade)
name (string)
description (text, nullable)
address (string)
city (string) — index this for filter queries
latitude (decimal 10,7, nullable)
longitude (decimal 10,7, nullable)
total_seats (integer)
price_per_month (decimal 8,2)
amenities (jsonb) — e.g. ["wifi","printer","meeting_room","parking","coffee"]
photos (jsonb) — array of S3/R2 paths
status (enum: 'pending', 'active', 'suspended', 'rejected') default 'pending'
avg_rating (decimal 3,2, default 0) — denormalized, updated on review save
created_at, updated_at
```

**`seats` table:**
```
id (uuid)
workspace_id (uuid, FK → workspaces.id, onDelete cascade)
seat_number (string) — e.g. "A1", "B3" — unique within workspace
type (enum: 'fixed', 'flexible', 'private_office')
status (enum: 'available', 'occupied', 'reserved', 'maintenance') default 'available'
assigned_member_id (uuid, FK → users.id, nullable)
notes (string, nullable)
created_at, updated_at
```

**`subscriptions` table:**
```
id (uuid)
member_id (uuid, FK → users.id)
workspace_id (uuid, FK → workspaces.id)
seat_id (uuid, FK → seats.id, nullable)
plan_type (enum: 'monthly', 'daily', 'custom')
start_date (date)
end_date (date, nullable)
monthly_price (decimal 8,2)
status (enum: 'active', 'cancelled', 'expired', 'pending')
cancelled_at (timestamp, nullable)
created_at, updated_at
```

**`invoices` table:**
```
id (uuid)
subscription_id (uuid, FK → subscriptions.id)
amount (decimal 8,2)
due_date (date)
paid_at (timestamp, nullable)
status (enum: 'pending', 'paid', 'overdue', 'cancelled') default 'pending'
invoice_number (string, unique) — format: TAQAT-{YYYY}-{0000}
invoice_pdf_path (string, nullable — S3/R2 path after generation)
notes (text, nullable)
created_at, updated_at
```

- Create Eloquent models for all four with relationships:
  - `Workspace` belongsTo `User` (owner), hasMany `Seat`, hasMany `Subscription`, hasMany `Review`
  - `Seat` belongsTo `Workspace`, belongsTo `User` (assigned_member, nullable)
  - `Subscription` belongsTo `User` (member), belongsTo `Workspace`, belongsTo `Seat`, hasMany `Invoice`
  - `Invoice` belongsTo `Subscription`

**Dependencies:** T006.

---

#### T008 — Supporting Table Migrations
**Type:** DB · **Priority:** P1 · **SP:** 5

**What to build:**  
Four migrations for communications and social features: internet packages, booking requests, messages, announcements, and reviews.

**Backend scope:**

**`internet_packages` table:**
```
id (uuid)
workspace_id (uuid, FK → workspaces.id, onDelete cascade)
name (string) — e.g. "Basic 10Mbps", "Unlimited Fiber"
speed_mbps (integer)
price (decimal 8,2) — monthly add-on price
data_limit_gb (integer, nullable — null = unlimited)
is_unlimited (boolean, default false)
is_active (boolean, default true)
created_at, updated_at
```

**`booking_requests` table:**
```
id (uuid)
member_id (uuid, FK → users.id)
workspace_id (uuid, FK → workspaces.id)
preferred_seat_type (enum: 'fixed','flexible','private_office', nullable)
message (text, nullable)
status (enum: 'pending','approved','rejected') default 'pending'
reviewed_by (uuid, FK → users.id, nullable)
rejection_reason (text, nullable)
reviewed_at (timestamp, nullable)
created_at, updated_at
```

**`messages` table:**
```
id (uuid)
sender_id (uuid, FK → users.id)
receiver_id (uuid, FK → users.id, nullable — null = broadcast)
workspace_id (uuid, FK → workspaces.id)
type (enum: 'direct', 'broadcast')
content (text)
read_at (timestamp, nullable)
created_at, updated_at
```

**`announcements` table:**
```
id (uuid)
workspace_id (uuid, FK → workspaces.id, nullable — null = platform-wide from admin)
title (string)
body (text)
type (enum: 'offer', 'info', 'alert', 'system')
published_at (timestamp, nullable — null = draft)
expires_at (timestamp, nullable)
created_by (uuid, FK → users.id)
created_at, updated_at
```

**`reviews` table:**
```
id (uuid)
member_id (uuid, FK → users.id)
workspace_id (uuid, FK → workspaces.id)
rating (tinyint, 1–5)
comment (text, nullable)
created_at, updated_at
UNIQUE constraint on (member_id, workspace_id) — one review per member per workspace
```

- Create all models with relationships.
- Add database index on `booking_requests.status`, `messages.receiver_id`, `announcements.workspace_id`, `announcements.expires_at`.

**Dependencies:** T007.

---

#### T009 — Database Seeders
**Type:** DB · **Priority:** P1 · **SP:** 3

**What to build:**  
Factories and seeders that produce a complete, realistic Gaza-context dataset for development and testing.

**Backend scope:**

Create factories for all 10 models. Seed data specifications:
- **1 admin** user: `admin@taqat.space`
- **5 workspace owners** with distinct Gaza-area businesses
- **20 freelancers** with realistic Arabic names and specialties (Web Developer, Graphic Designer, Content Writer, Accountant, Marketing Specialist, etc.)
- **5 workspaces** in different Gaza neighborhoods (Rimal, Sheikh Radwan, Jabalia, Beit Lahia, Khan Younis). Each has 10–20 seats, photos array, amenities.
- Workspace statuses: 3 active, 1 pending, 1 suspended — to test admin approval flows.
- **Subscriptions:** 30 active subscriptions across the active workspaces, with varied start dates.
- **Invoices:** 3 months of invoice history per subscription. Mix of paid, pending, and overdue statuses.
- **Booking requests:** 10 pending, 5 approved, 3 rejected.
- **Messages:** 20 direct messages + 5 broadcast announcements per active workspace.
- **Reviews:** 15 reviews distributed across workspaces with varied ratings.

All Arabic name/address data must be linguistically correct. Use a hardcoded array of real Gaza city names — do not use Faker's default Western names.

**Dependencies:** T007, T008.

---

#### T010 — Registration Endpoint
**Type:** BE · **Priority:** P1 · **SP:** 5

**What to build:**  
A single `POST /api/auth/register` endpoint that handles registration for all three roles. Role is submitted in the request body. Workspace owners enter a pending state awaiting admin approval.

**Backend scope:**
- `RegisterController@register` method.
- Create `RegisterRequest` FormRequest with role-conditional validation rules:
  - All roles: `name` (required, string, 2–100), `email` (required, email, unique:users), `password` (required, min:8, confirmed), `role` (required, in: freelancer, workspace_owner).
  - Freelancer only: `specialty` (optional, string).
  - Workspace owner only (multipart/form-data for file upload): `license_file` (required, file, mimes:pdf,jpg,png, max:5MB), `id_document` (required, file, mimes:pdf,jpg,png, max:5MB).
  - Note: Admin accounts are created manually — no public registration for admin role.
- On success:
  - Create `User` record. Hash password via `bcrypt`.
  - Assign Spatie role.
  - Freelancer: status = `pending_verification`. Send email verification.
  - Workspace owner: status = `pending_verification`. Store documents to S3/R2 via Flysystem. Queue notification to admin that new workspace registration needs review. Send confirmation email to owner.
  - Return: `{ user: UserResource, token: string, role: string }` with HTTP 201.
- Return `422` with field-level validation errors for failed validation.

**API:** `POST /api/auth/register`  
**Dependencies:** T006, T005.

---

#### T011 — Login, Logout & Token Management
**Type:** BE · **Priority:** P1 · **SP:** 3

**What to build:**  
Standard Sanctum token-based auth for the SPA. Login returns a token; logout revokes it.

**Backend scope:**
- `AuthController@login`:
  - Validate: `email` (required, email), `password` (required).
  - Attempt auth via `Auth::attempt()`. Return `401` if credentials invalid.
  - Check `user->status`. If `suspended`, return `403` with `{ message: "Account suspended" }`. If `pending_verification`, return `403` with `{ message: "Account pending verification" }`.
  - Create Sanctum token: `$user->createToken('auth_token')->plainTextToken`.
  - Return: `{ user: UserResource, token: string, token_type: "Bearer", role: string }`.
- `AuthController@logout`:
  - Auth: `auth:sanctum` middleware.
  - Revoke current token: `$request->user()->currentAccessToken()->delete()`.
  - Return `204 No Content`.
- `AuthController@me`:
  - Return authenticated user resource. Used by Next.js on app load to validate stored token.

**API:** `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`  
**Dependencies:** T010.

---

#### T012 — Email Verification & Password Reset
**Type:** BE · **Priority:** P1 · **SP:** 3

**What to build:**  
Email verification on registration and a full password reset flow. Both use queued Mailgun jobs to avoid blocking the request.

**Backend scope:**
- Implement `MustVerifyEmail` on the `User` model.
- Create bilingual email templates (Arabic + English) for:
  - Verification email: subject "تحقق من بريدك الإلكتروني / Verify your email"
  - Password reset email: subject "إعادة تعيين كلمة المرور / Reset your password"
- Configure `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN=taqat.space`, `MAILGUN_SECRET` in `.env`. Set `MAIL_FROM_ADDRESS=noreply@taqat.space`.
- `AuthController@forgotPassword`: accept `email`, throttle to 3 requests/hour via `RateLimiter`, create `password_resets` record, dispatch `ResetPasswordMail` job to queue.
- `AuthController@resetPassword`: validate `token`, `email`, `password`, `password_confirmation`. Reset password and invalidate all existing Sanctum tokens for that user.
- `AuthController@verifyEmail`: standard Laravel email verification endpoint. On success, update `email_verified_at` and `status` to `active` for freelancers.

**API:** `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`, `GET /api/auth/verify-email/{id}/{hash}`  
**Dependencies:** T011.

---

#### T013 — Role-Based Gates & Middleware
**Type:** BE · **Priority:** P1 · **SP:** 3

**What to build:**  
Three middleware classes that protect route groups based on user role. All protected routes also require an active account status.

**Backend scope:**
- Create three middleware: `EnsureFreelancer`, `EnsureWorkspaceOwner`, `EnsureAdmin`.
- Each middleware:
  1. Calls `auth:sanctum` — if unauthenticated, return `401`.
  2. Checks `$user->status === UserStatus::Active`. If suspended, return `403 { message: "Account suspended" }`.
  3. Checks role. If wrong role, return `403 { message: "Insufficient permissions" }`.
- Register in `bootstrap/app.php` middleware aliases: `role.freelancer`, `role.owner`, `role.admin`.
- Apply to route groups in `api.php`:
  ```php
  Route::middleware(['auth:sanctum', 'role.owner'])->prefix('workspace')->group(...);
  Route::middleware(['auth:sanctum', 'role.freelancer'])->prefix('member')->group(...);
  Route::middleware(['auth:sanctum', 'role.admin'])->prefix('admin')->group(...);
  ```
- Define Laravel Gates for cross-role actions (e.g., admin can view any workspace):
  - `Gate::define('manage-workspace', fn(User $user, Workspace $ws) => $user->id === $ws->owner_id || $user->isAdmin())`

**Dependencies:** T011.

---

#### T014 — Next.js Auth Layer
**Type:** FE · **Priority:** P1 · **SP:** 5

**What to build:**  
Client-side authentication foundation: token storage, a global `AuthProvider`, route protection middleware, and a typed API client.

**Frontend scope:**
- **Token storage strategy:** Store the Sanctum token in an `httpOnly` cookie via a Next.js API route (`/api/set-token`) to prevent XSS access. Never store in `localStorage`.
- **`AuthProvider` (`components/providers/AuthProvider.tsx`):**
  - On mount, call `GET /api/auth/me` with the stored token. Populate `AuthContext` with `{ user, role, isLoading, isAuthenticated }`.
  - Expose `login(email, password)`, `logout()`, `refreshUser()` functions.
- **`middleware.ts` (Next.js route middleware):**
  - Read token from cookie.
  - Unauthenticated users hitting `/(dashboard)/*` → redirect to `/auth/login`.
  - Authenticated users with wrong role hitting a dashboard → redirect to their own dashboard.
  - Authenticated users hitting `/(auth)/*` → redirect to their dashboard.
- **Typed API client (`lib/api.ts`):**
  - Wrapper around `fetch` that injects `Authorization: Bearer {token}`, sets `Content-Type: application/json`, handles `401` by clearing auth + redirecting to login, handles `422` by returning typed `ValidationError` structure.
  - All API functions typed with their response shapes.
- **Route structure:**
  - `/dashboard/owner/*` → `EnsureRole('workspace_owner')`
  - `/dashboard/freelancer/*` → `EnsureRole('freelancer')`
  - `/dashboard/admin/*` → `EnsureRole('admin')`

**Dependencies:** T011.

---

#### T015 — Login Page
**Type:** FE · **Priority:** P1 · **SP:** 3

**What to build:**  
The login page with full bilingual support, error handling, and loading states.

**Frontend scope:**
- Route: `app/(auth)/login/page.tsx`
- Layout: centered card on a neutral background with the TAQAT logo tile. Clean, no decorative elements.
- Form fields: Email (type=email), Password (with show/hide toggle), Submit button.
- Form state: managed with React Hook Form + Zod schema validation. Inline error messages below each field.
- On submit: call `AuthProvider.login()`. Show spinner on the button. On success, redirect to the appropriate dashboard based on `role`. On `401`, display "البريد الإلكتروني أو كلمة المرور غير صحيحة / Invalid email or password". On `403` (suspended), display appropriate message.
- Language toggle in the header updates `dir` and re-renders all labels.
- "Forgot password?" link → `/auth/forgot-password`.
- "Create account" links for both register types.

**API:** `POST /api/auth/login`  
**Dependencies:** T014.

---

#### T016 — Register as Freelancer (Multi-step)
**Type:** FE · **Priority:** P1 · **SP:** 5

**What to build:**  
Three-step registration form for freelancers. Progress is tracked visually. No data is submitted until the final step.

**Frontend scope:**
- Route: `app/(auth)/register/freelancer/page.tsx`
- Step 1 — Personal Info: Full name (Arabic), email, password, confirm password.
- Step 2 — Professional Details: Specialty (dropdown: Web Developer, Mobile Developer, Graphic Designer, Content Writer, Marketing Specialist, Accountant, Data Analyst, Other), short bio (optional).
- Step 3 — Identity: Upload national ID photo (file input, preview before submit, max 5MB, JPG/PNG/PDF).
- Progress indicator: numbered steps with the tile motif. Current step highlighted in brand blue.
- Validation: Zod schema per step. "Next" button disabled until current step is valid.
- On final submit: POST all collected data as `multipart/form-data`. Show full-page loading state. On success: show "Account created — check your email to verify" screen.

**API:** `POST /api/auth/register`  
**Dependencies:** T014.

---

#### T017 — Register as Workspace (Multi-step)
**Type:** FE · **Priority:** P1 · **SP:** 5

**What to build:**  
Four-step registration for workspace owners. Ends with a "pending review" confirmation screen instead of immediate access.

**Frontend scope:**
- Route: `app/(auth)/register/workspace/page.tsx`
- Step 1 — Space Info: Workspace name, description (textarea), city (dropdown of Gaza areas), full address, phone.
- Step 2 — Location: Embed Leaflet map. User clicks to drop a pin. Latitude/longitude auto-filled from pin. Display address confirmation.
- Step 3 — Seats & Pricing: Total seat count (number input), base monthly price per seat (number + currency selector ₪/USD), seat types available (checkboxes: Fixed / Flexible / Private Office), list of amenities (multi-select checkboxes with icons: WiFi, Printer, Meeting Room, Parking, Coffee, Kitchen, 24/7 Access).
- Step 4 — Documents: Upload business license (PDF/JPG, max 5MB) + owner ID (PDF/JPG, max 5MB). File upload component shows preview/filename + remove button.
- On submit: POST as `multipart/form-data`. On success: show a full-screen "Pending Review" state — the tile logo, a waiting message in Arabic, and instruction to check email. No dashboard access until admin approves.

**API:** `POST /api/auth/register`  
**Dependencies:** T014.

---

#### T018 — Forgot & Reset Password Pages
**Type:** FE · **Priority:** P1 · **SP:** 2

**What to build:**  
Two simple pages for the password reset flow.

**Frontend scope:**
- `/auth/forgot-password`: Single email field. On submit, show "If that email exists, a reset link has been sent." Prevent repeated submits (disable button for 60s after send).
- `/auth/reset-password`: Token from URL query param. Fields: new password + confirm. On success, redirect to login with a success toast. On invalid/expired token, show an error state with a link to request a new reset.

**API:** `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`  
**Dependencies:** T014.

---

---

# Phase 2 — Core Dashboards

> Public discovery site and the primary dashboards for Workspace Owners and Freelancers. No invoicing, messaging, or notifications yet — those come in Phase 3.

---

## Milestone M04 — Public Marketing Site

**Goal:** Any visitor (no account required) can discover TAQAT, browse workspaces on a map and list, view detailed workspace pages, and register as either role.

**Success criteria:** All pages render correctly in Arabic (RTL) and English (LTR). Leaflet map loads and shows workspace pins. Workspace filter returns correct results. Lighthouse performance ≥80.

---

### Sprint 3 — Tasks T019–T026

---

#### T019 — Home Page
**Type:** FE · **Priority:** P2 · **SP:** 5

**What to build:**  
The marketing home page. High visual impact but structurally minimal. Designed to convert both workspace owners and freelancers.

**Frontend scope:**
- Route: `app/(public)/page.tsx`
- **Hero section:** Full-width, brand blue gradient background. TAQAT logotype tiles (boxed letters) large and prominent. Headline: "مساحتك المهنية في غزة / Your professional space in Gaza". Two CTA buttons: "ابحث عن مساحة" (primary) → `/explore`, "سجل مساحتك" (secondary/outline) → `/register/workspace`.
- **Platform stats tiles:** Fetch from `/api/workspaces?featured=1`. Show animated count tiles: "X مساحة عمل نشطة / Active Spaces", "X فريلانسر / Freelancers", "X% متوسط إشغال / Avg Occupancy". Tiles use the signature rounded-square tile motif.
- **Featured workspaces:** Horizontal card scroll (mobile) / 3-column grid (desktop). Each card: photo, name, city, price/month, rating stars, "احجز / Book" CTA.
- **How it works:** 3 steps with numbered tile icons: (1) Create account → (2) Find your space → (3) Manage your work.
- **Footer:** Links, language toggle, "عن المنصة / About", contact.

**API:** `GET /api/workspaces?featured=1&limit=6`  
**Dependencies:** T014, T024.

---

#### T020 — Explore Workspaces Page
**Type:** FE · **Priority:** P2 · **SP:** 8

**What to build:**  
The main discovery surface. Split view: workspace list on one side, interactive map on the other. Heavy filtering capability.

**Frontend scope:**
- Route: `app/(public)/explore/page.tsx`
- **Layout:** Sticky filter bar at top. Below: 40% list / 60% map split on desktop. Full list with map toggle button on mobile.
- **Leaflet map (`react-leaflet`):**
  - Use OpenStreetMap tiles (no API key required).
  - Custom blue pin marker using the TAQAT dot icon.
  - Clicking a pin highlights the corresponding card in the list and opens a small popup with workspace name, price, and "View details" link.
  - Map is centered on Gaza Strip by default (`lat: 31.5, lng: 34.47`, zoom 12).
- **Filter bar (`components/features/FilterBar.tsx`):**
  - Text search (debounced 300ms).
  - City multi-select dropdown (populated from distinct cities in API).
  - Price range: dual-handle slider (min/max monthly price).
  - Amenities: checkbox group (WiFi, Printer, Meeting Room, etc.).
  - Seat type: checkbox group (Fixed, Flexible, Private Office).
  - Minimum rating: star selector (1–5).
  - "Clear filters" button.
- **Workspace list:** Virtualized list (use `react-window` if >50 items). Each card shows photo, name, city, seat type badges, price, rating, "View" button.
- Filter state lives in URL query params so filters are shareable/bookmarkable.
- Loading state: skeleton cards + spinner on map.
- Empty state: illustrated tile + "لا توجد مساحات مطابقة / No matching spaces" + "Clear filters" CTA.

**API:** `GET /api/workspaces` (with filter query params)  
**Dependencies:** T019, T024.

---

#### T021 — Workspace Detail Page
**Type:** FE · **Priority:** P2 · **SP:** 5

**What to build:**  
Full workspace profile page. Converts a visitor to a booking request.

**Frontend scope:**
- Route: `app/(public)/workspaces/[id]/page.tsx`
- **Photo gallery:** Swipeable carousel (mobile), lightbox on click (desktop). If no photos, show a branded placeholder.
- **Info section:** Name, city, address with mini-map (Leaflet, non-interactive), phone.
- **Amenities list:** Icon + label chips (the tile motif). Display all amenities from the `amenities` JSON array.
- **Seat types & pricing:** Card per seat type showing type name, price/month, availability (count of available seats). "احجز هذا النوع / Book this type" CTA per card.
- **Reviews section:** Average rating (large number + stars). List of reviews (reviewer name, rating, comment, date). Paginated. `ReviewForm` component (shown only to authenticated freelancers who have an active/past subscription).
- **Sticky booking panel (desktop):** Floats on the right while scrolling. Shows price, available seats count, and "طلب حجز / Request Booking" CTA. If not logged in, CTA → login page with redirect-back.
- **SEO:** `generateMetadata()` function that returns Arabic title and description for each workspace.

**API:** `GET /api/workspaces/:id`, `GET /api/workspaces/:id/reviews`  
**Dependencies:** T023, T057.

---

#### T022 — About, FAQ & Contact Pages
**Type:** FE · **Priority:** P2 · **SP:** 2

**What to build:**  
Three static content pages. Content managed in the translation files (`messages/ar.json`, `messages/en.json`).

**Frontend scope:**
- **About (`/about`):** Platform story, mission, the Gaza context, team section. Clean editorial layout, no complex components.
- **FAQ (`/faq`):** Segmented by role: "للفريلانسر / For Freelancers", "لأصحاب المساحات / For Workspace Owners". Accordion component per question/answer. Questions sourced from `messages/ar.json`.
- **Contact (`/contact`):** Contact form (name, email, subject, message). On submit: POST to a Laravel `ContactController` that queues an email. No external form service. Show success state after submit.

**Dependencies:** T019.

---

#### T023 — Workspaces CRUD API
**Type:** BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The complete workspace management API used by owners (create/update their space) and the public (read workspaces).

**Backend scope:**
- `WorkspaceController` with:
  - `index` (public): returns paginated workspaces. Only `status = 'active'` visible publicly.
  - `show` (public): returns one workspace with seats summary, avg rating, and photos.
  - `store` (owner, auth): create workspace. Status defaults to `pending` (awaits admin approval). Owner can only create one workspace per account.
  - `update` (owner, auth): update own workspace. Policy: `$user->id === $workspace->owner_id`.
  - `destroy` (admin only): soft delete.
- `WorkspaceResource` and `WorkspaceCollection` API resources for consistent response shaping.
- `WorkspacePolicy` enforcing ownership.
- Status management endpoint (`PUT /api/admin/workspaces/:id/status`) lives in Admin routes but is defined here.

**API:** `GET /api/workspaces`, `GET /api/workspaces/:id`, `POST /api/workspace/create`, `PUT /api/workspace/settings`  
**Dependencies:** T007, T013.

---

#### T024 — Workspace Search & Filtering
**Type:** BE · **Priority:** P2 · **SP:** 5

**What to build:**  
Efficient server-side filtering for the explore page. All filters are optional and composable.

**Backend scope:**
- Add query scopes to the `Workspace` model:
  - `scopeInCity(string $city)`: `where('city', $city)`
  - `scopePriceRange(float $min, float $max)`: `whereBetween('price_per_month', [$min, $max])`
  - `scopeWithAmenities(array $amenities)`: Use Postgres JSONB containment: `whereJsonContains('amenities', $amenity)` for each amenity (chained `where`).
  - `scopeMinRating(float $rating)`: `where('avg_rating', '>=', $rating)`
  - `scopeSearch(string $query)`: `where('name', 'ilike', "%{$query}%")->orWhere('description', 'ilike', "%{$query}%")`
- In `WorkspaceController@index`: apply scopes conditionally based on presence of query params. Always paginate (15 per page). Sort: `?sort=price_asc`, `price_desc`, `rating_desc`, `newest`.
- Add a `GET /api/workspaces/cities` endpoint returning distinct active city names — used to populate the city filter dropdown.
- Add database indexes: `workspaces.city`, `workspaces.price_per_month`, `workspaces.avg_rating`, `workspaces.status`.

**API:** `GET /api/workspaces?city=&min_price=&max_price=&amenities[]=&min_rating=&search=&sort=&page=`  
**Dependencies:** T023.

---

#### T025 — File Storage for Workspace Photos
**Type:** BE · **Priority:** P2 · **SP:** 3

**What to build:**  
Photo upload and management for workspace profiles. Photos stored in Cloudflare R2 (S3-compatible) via Laravel Flysystem.

**Backend scope:**
- Configure `config/filesystems.php` with an `r2` disk:
  ```php
  'r2' => [
      'driver' => 's3',
      'key' => env('CLOUDFLARE_R2_ACCESS_KEY'),
      'secret' => env('CLOUDFLARE_R2_SECRET'),
      'region' => 'auto',
      'bucket' => env('CLOUDFLARE_R2_BUCKET'),
      'url' => env('CLOUDFLARE_R2_URL'),
      'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
      'use_path_style_endpoint' => true,
  ]
  ```
- `PhotoController@store` (owner, auth):
  - Validate: `photos.*` (file, image, max 5MB, mimes: jpg, png, webp). Max 10 photos per workspace.
  - Upload to R2 at path: `workspaces/{workspace_id}/{uuid}.{ext}`.
  - Append the R2 URL to `workspaces.photos` JSONB array.
- `PhotoController@destroy`: remove from R2 + remove from JSONB array.
- When returning workspace data, generate signed URLs if R2 bucket is private (preferred) or use public URLs if bucket is public.

**API:** `POST /api/workspace/photos`, `DELETE /api/workspace/photos/{path}`  
**Dependencies:** T023.

---

#### T026 — Seats API
**Type:** BE · **Priority:** P2 · **SP:** 5

**What to build:**  
Complete seat management API. The seat map on the frontend consumes this endpoint to render real-time seat state.

**Backend scope:**
- `SeatController` with:
  - `index` (owner + public): return all seats for a workspace with their current status and assignee info (name only, not full user data). Group by type for the seat map.
  - `store` (owner): create a new seat. Validate `seat_number` uniqueness within the workspace. Maximum seats = `workspace.total_seats`.
  - `update` (owner): update seat type, status, notes.
  - `assign` (owner): assign a seat to a member. Validates: member must have an active subscription to this workspace. Updates `seat.status = 'occupied'` and `seat.assigned_member_id`. Dispatches a notification to the member.
  - `unassign` (owner): sets `assigned_member_id = null` and `status = 'available'`.
  - `destroy` (owner): only allowed if seat is `available` and has no subscription history.
- Return shape for seat map:
  ```json
  {
    "seats": [
      {
        "id": "uuid",
        "seat_number": "A1",
        "type": "fixed",
        "status": "available|occupied|reserved|maintenance",
        "assigned_member": { "id": "uuid", "name": "Ahmed Ali" } | null
      }
    ],
    "summary": { "total": 20, "available": 8, "occupied": 11, "maintenance": 1 }
  }
  ```

**API:** `GET /api/workspaces/:id/seats`, `POST /api/workspace/seats`, `PUT /api/seats/:id`, `PUT /api/seats/:id/assign`, `PUT /api/seats/:id/unassign`  
**Dependencies:** T007, T013.

---

## Milestone M05 — Workspace Owner Dashboard Core

**Goal:** A workspace owner can manage their full member lifecycle from their dashboard: view stats, accept/suspend members, visualize and assign seats, and process booking requests.

---

### Sprint 4 — Tasks T027–T032

---

#### T027 — Owner Dashboard Home (Stats)
**Type:** FE+BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The first screen the owner sees on login — a real-time snapshot of their workspace's operational state.

**Backend scope:**
- `DashboardController@ownerStats` returns a single aggregated object:
  ```json
  {
    "occupancy_pct": 75.0,
    "active_members": 15,
    "available_seats": 5,
    "pending_bookings": 3,
    "overdue_invoices": 2,
    "revenue_this_month": 1250.00,
    "revenue_last_month": 1100.00,
    "revenue_chart": [ { "month": "2025-01", "amount": 900 }, ... ] // last 6 months
  }
  ```
- All aggregations done via Eloquent query scopes. Cache result for 5 minutes in Redis (key: `workspace:{id}:dashboard_stats`). Bust cache on invoice payment, seat assignment, or booking status change.

**Frontend scope:**
- Route: `app/(dashboard)/owner/page.tsx`
- **Stat tiles row:** Occupancy %, Active Members, Available Seats, Pending Bookings, Overdue Invoices. Use the rounded-tile component from the design system. Overdue Invoices and Pending Bookings tiles pulse in amber if count > 0.
- **Revenue chart:** `Recharts` `AreaChart` showing last 6 months. Blue fill, neutral gridlines.
- **Quick action links:** "Review Bookings" (if pending > 0), "Issue Invoices", "View Members".
- Loading state: skeleton tiles (animated gray placeholders).

**API:** `GET /api/workspace/dashboard`  
**Dependencies:** T023, T026, T013.

---

#### T028 — Members Management
**Type:** FE+BE · **Priority:** P2 · **SP:** 8

**What to build:**  
Full member roster for the workspace owner: see all current and past members, accept or suspend accounts, and view member details.

**Backend scope:**
- `MemberController` (under `role.owner` middleware):
  - `index`: paginated list of subscriptions for this workspace, joined with user data. Filter by `status` (active/suspended/all). Search by name/email. Sort by join date, name, subscription status.
  - `show`: full member detail — subscription info, seat assignment, all invoices, payment history.
  - `updateStatus`: activate or suspend a member. If suspended, mark subscription as `cancelled`, unassign seat, fire `MemberSuspended` event.
- Ensure queries scope to `workspace_id = auth user's workspace` — never return members from other workspaces.

**Frontend scope:**
- Route: `app/(dashboard)/owner/members/`
- **Members table:** Columns: Avatar + Name, Subscription Status badge, Seat Number, Monthly Price, Join Date, Actions. Sortable columns. Sticky header on scroll. Row click → opens member detail drawer.
- **Filter bar:** Search input, status filter dropdown, sort selector.
- **Member detail drawer (`components/features/MemberDrawer.tsx`):** Slides in from the right (left in LTR). Shows: avatar, name, email, phone, specialty, subscription details, seat info, payment history (mini invoice list), and action buttons (Assign Seat, Suspend, Send Message).
- **Suspend confirmation modal:** Confirm dialog before suspending. Option to add a note.
- Empty state: illustrated "لا يوجد مشتركون بعد / No members yet" with a link to share the workspace page.

**API:** `GET /api/workspace/members`, `GET /api/workspace/members/:id`, `PUT /api/workspace/members/:id/status`  
**Dependencies:** T026, T027.

---

#### T029 — Interactive Seat Map (Frontend)
**Type:** FE · **Priority:** P2 · **SP:** 8

**What to build:**  
The signature visual interaction of the entire platform. A grid of tile-shaped seat components, each showing real-time status. This is the component that makes the platform feel like a real operations tool.

**Frontend scope:**
- Route: `app/(dashboard)/owner/seats/` — includes the seat map.
- **`SeatMap` component (`components/features/SeatMap.tsx`):**
  - Renders a CSS Grid where each cell is a `SeatTile` component.
  - Grid is responsive: auto-fill columns with `minmax(80px, 1fr)`.
  - Seat tiles are the same rounded-square shape from the logo motif (14px radius).
- **`SeatTile` component:**
  - Size: 72×72px (desktop), 56×56px (mobile).
  - States with distinct visual treatment:
    - `available`: white background, blue border, seat number in blue.
    - `occupied`: brand blue fill, white seat number + member initials avatar.
    - `selected` (during assignment flow): amber fill, animated pulse ring.
    - `reserved`: light blue fill, dashed border.
    - `maintenance`: neutral gray fill, wrench icon.
  - Hover: tooltip showing seat number, type, and assignee name (if occupied).
  - Click: if `available` → enter "select" mode (for assignment). If `occupied` → open member mini-card popover.
- **Legend row:** horizontal legend at the top of the map explaining all states.
- **Summary bar:** "Available: X · Occupied: X · Maintenance: X" updated reactively.
- The seat map re-fetches and re-renders when the owner completes an assignment action (T030).

**API:** `GET /api/workspaces/:id/seats`  
**Dependencies:** T026.

---

#### T030 — Seat Assignment Flow
**Type:** FE+BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The complete flow for assigning or unassigning a seat to a member, triggered from the seat map.

**Backend scope:**
- `SeatController@assign`:
  - Validate: `member_id` must be a user with an active subscription to this workspace, and not already assigned a seat.
  - Update `seat.status = 'occupied'`, `seat.assigned_member_id = member_id`.
  - Dispatch `SeatAssigned` notification to the member.
  - Return updated seat resource.
- `SeatController@unassign`:
  - Reset `seat.status = 'available'`, `seat.assigned_member_id = null`.
  - Dispatch `SeatUnassigned` notification to the member.

**Frontend scope:**
- **Assignment modal (`components/features/AssignSeatModal.tsx`):**
  - Triggered when owner clicks an available seat tile in the seat map.
  - Shows: selected seat number + type. Dropdown of unassigned members (with avatar + name). Confirm button.
  - On confirm: `PUT /api/seats/:id/assign`. On success: close modal, update seat tile state optimistically, show success toast.
- **Unassign flow:** Clicking an occupied seat shows a popover with member info + "Unassign Seat" button. Confirmation in-popover. Updates tile state on success.

**API:** `PUT /api/seats/:id/assign`, `PUT /api/seats/:id/unassign`  
**Dependencies:** T028, T029.

---

#### T031 — Booking Requests Queue
**Type:** FE+BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The incoming booking request queue for workspace owners. Every booking request submitted by a freelancer appears here for the owner to approve or reject.

**Backend scope:**
- `BookingRequestController` (owner scope):
  - `index`: paginated list of booking requests for this workspace. Filter by status. Order by `created_at desc`.
  - `update` (approve/reject):
    - If `action = approve`:
      - Create a `Subscription` record (status: active, start_date: today, seat assigned if `seat_id` provided).
      - Update `booking_request.status = 'approved'`, `reviewed_by = auth user id`, `reviewed_at = now()`.
      - Dispatch `BookingApproved` notification to freelancer.
    - If `action = reject`:
      - Update `booking_request.status = 'rejected'`, `rejection_reason`, `reviewed_at = now()`.
      - Dispatch `BookingRejected` notification to freelancer.

**Frontend scope:**
- Route: `app/(dashboard)/owner/bookings/`
- **Requests table:** Columns: Freelancer name + avatar, specialty, preferred seat type, message (truncated), submitted date, status badge, Actions.
- **Approve modal:** Select a seat from a compact seat-picker (mini version of seat map, only available seats). Confirm button. On approve, row moves to "Approved" tab.
- **Reject modal:** Textarea for rejection reason (optional). Confirm button.
- **Tabs:** Pending / Approved / Rejected. Badge count on "Pending" tab.

**API:** `GET /api/workspace/booking-requests`, `PUT /api/workspace/booking-requests/:id`  
**Dependencies:** T026, T013.

---

#### T032 — Space Settings
**Type:** FE+BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The owner's settings page for updating their workspace profile — the same information entered during registration, now editable.

**Backend scope:**
- `WorkspaceController@updateSettings` (owner, auth):
  - Allow updating: name, description, address, city, latitude, longitude, price_per_month, amenities, working hours (add a `working_hours` JSON column if not already present), total_seats.
  - Do not allow changing `status` or `owner_id` via this endpoint.
  - Validate all fields. Return updated `WorkspaceResource`.
- Photo management: handled by T025 endpoints.

**Frontend scope:**
- Route: `app/(dashboard)/owner/settings/`
- **Tabbed settings layout:**
  - Tab 1 — Basic Info: Name, description, city, address, phone.
  - Tab 2 — Location: Editable Leaflet map pin.
  - Tab 3 — Pricing & Seats: Price per seat, total seat count, seat types offered.
  - Tab 4 — Amenities: Checkbox grid with icons.
  - Tab 5 — Photos: Drag-and-drop photo manager. Shows current photos with delete buttons. Upload new photos.
  - Tab 6 — Hours: Day-of-week hours editor (open/close time per day, or "Closed").
- Each tab has its own Save button to avoid losing unrelated changes.

**API:** `PUT /api/workspace/settings`, `POST /api/workspace/photos`  
**Dependencies:** T023, T025.

---

## Milestone M06 — Freelancer Dashboard Core

**Goal:** A freelancer can discover workspaces, submit booking requests, view their subscription and assigned seat, and manage their profile.

---

### Sprint 4–5 — Tasks T033–T039

---

#### T033 — Freelancer Dashboard Home
**Type:** FE+BE · **Priority:** P2 · **SP:** 3

**What to build:**  
The freelancer's landing screen after login. Focused on the current subscription and upcoming obligations.

**Backend scope:**
- `MemberDashboardController@summary`:
  ```json
  {
    "subscription": { "status": "active", "workspace_name": "...", "plan_type": "monthly", "end_date": "..." } | null,
    "seat": { "seat_number": "A3", "type": "fixed" } | null,
    "next_invoice": { "amount": 150.00, "due_date": "..." } | null,
    "unread_notifications": 3,
    "pending_booking_requests": 1
  }
  ```

**Frontend scope:**
- Route: `app/(dashboard)/freelancer/page.tsx`
- If no active subscription: Show "استكشف مساحات العمل / Explore Workspaces" CTA prominently. Show pending booking request status if one exists.
- If active subscription: Show subscription card (workspace name, seat, plan type, renewal date), next invoice card (amount + due date), and a quick link to the workspace page.
- Notification bell with unread count.

**API:** `GET /api/member/dashboard`  
**Dependencies:** T034, T014.

---

#### T034 — My Subscription & Bookings
**Type:** FE+BE · **Priority:** P2 · **SP:** 5

**What to build:**  
Detailed subscription management for the freelancer — view plan details, request a change, or initiate cancellation.

**Backend scope:**
- `SubscriptionController`:
  - `index` (member): all subscriptions (active + history) for this user.
  - `show` (member): full detail including seat, workspace, payment history.
  - `requestChange`: creates a change-request notification to the owner. No direct mutation — owner must re-approve.
  - `cancel`: marks subscription as `cancelled`, adds `cancelled_at`. Unassigns seat. Does not cancel unpaid invoices (those remain as outstanding obligations).

**Frontend scope:**
- Route: `app/(dashboard)/freelancer/subscription/`
- Active subscription card: workspace name + photo, seat number, plan type, start date, renewal date, monthly price.
- "Request Change" button → modal with a textarea to explain the requested change. Sends notification to owner.
- "Cancel Subscription" button → confirmation modal explaining consequences (outstanding invoices, seat loss).
- Subscription history table: past workspaces, dates, status.

**API:** `GET /api/member/subscriptions`, `PUT /api/member/subscriptions/:id/cancel`  
**Dependencies:** T007, T013.

---

#### T035 — Freelancer Profile
**Type:** FE+BE · **Priority:** P2 · **SP:** 3

**What to build:**  
Profile editing for freelancers — name, specialty, avatar, and password change.

**Backend scope:**
- `UserController@updateProfile` (member):
  - Updateable: name, phone, specialty, avatar (file upload → R2).
  - Password change: requires `current_password` verification before accepting `new_password`.

**Frontend scope:**
- Route: `app/(dashboard)/freelancer/profile/`
- Avatar: circular upload zone with preview. Click to upload. Stores to R2.
- Fields: full name, phone, specialty (same dropdown as registration), bio.
- Separate "Change Password" section: current password + new password + confirm.
- Save buttons per section. Success toast on update.

**API:** `PUT /api/member/profile`  
**Dependencies:** T006, T014.

---

#### T036 — Booking Request Submit (Freelancer)
**Type:** BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The backend for a freelancer submitting a booking request from any workspace page.

**Backend scope:**
- `BookingRequestController@store` (freelancer, auth):
  - Validate: `workspace_id` exists and is active. Freelancer cannot have more than 1 pending booking request at a time (to prevent spam). Freelancer cannot submit a request to a workspace where they already have an active subscription.
  - Create `booking_request` record with `status = 'pending'`.
  - Dispatch `NewBookingRequest` notification to the workspace owner.
  - Return: `{ booking_request }` with HTTP 201.

**API:** `POST /api/booking-requests`  
**Dependencies:** T008, T013.

---

#### T037 — Booking Approval Logic (Backend)
**Type:** BE · **Priority:** P2 · **SP:** 5

**What to build:**  
The complete state machine for booking request approval. This is the most consequential business logic in Phase 2 — approve creates the subscription.

**Backend scope:**
- `BookingRequestController@update` (owner, auth) — referenced in T031 but the logic is here:
  - Wrap in a database transaction.
  - On approve:
    1. Verify the seat being assigned is still available (race condition check).
    2. Create `Subscription`: `member_id`, `workspace_id`, `seat_id`, `plan_type = 'monthly'`, `start_date = today`, `monthly_price = workspace.price_per_month`, `status = 'active'`.
    3. Update `Seat`: `status = 'occupied'`, `assigned_member_id = member_id`.
    4. Update `BookingRequest`: `status = 'approved'`, `reviewed_by`, `reviewed_at`.
    5. Dispatch first-invoice creation (or trigger via the scheduler — see T040).
    6. Dispatch `BookingApproved` notification to member.
  - On reject:
    1. Update `BookingRequest`: `status = 'rejected'`, `rejection_reason`, `reviewed_at`.
    2. Dispatch `BookingRejected` notification to member.
  - On race condition (seat taken between UI load and approval click): return `409 Conflict` with message prompting owner to select a different seat.

**Dependencies:** T031, T007.

---

#### T038 & T039 — Internet Packages (CRUD + Assignment)
**Type:** FE+BE · **Priority:** P3 · **SP:** 3+3

**What to build:**  
Allow workspace owners to define internet packages and assign them to members as add-ons.

**Backend scope (T038):**
- `PackageController` (owner): full CRUD. Validate `speed_mbps > 0`, `price >= 0`, `data_limit_gb > 0 || is_unlimited = true`.
- Packages belong to a workspace and can be assigned to multiple members simultaneously.

**Backend scope (T039):**
- `PackageController@assign`: create `member_package` pivot record (`member_id`, `package_id`, `assigned_at`). No limit on package assignments.
- `PackageController@unassign`: remove pivot record.

**Frontend scope:**
- Route: `app/(dashboard)/owner/internet/`
- Package list: cards with speed, data limit, price. "Edit" and "Delete" inline actions.
- "Add Package" → form modal: name, speed (Mbps), monthly price, data limit (GB) / "Unlimited" toggle.
- Each package card has an "Assign to Members" button → member multi-select dropdown.
- Assigned members list per package with "Remove" action.

**API:** `GET/POST/PUT/DELETE /api/workspace/packages`, `PUT /api/workspace/packages/:id/assign`  
**Dependencies:** T007, T028.

---

---

# Phase 3 — Invoicing, Messaging & Communications

> Adds the financial layer (automated invoices, PDF export, payment tracking) and the communications layer (real-time messages, notifications, announcements). This phase makes the platform operationally complete.

---

## Milestone M07 — Invoicing System

**Goal:** Invoices are generated automatically every month per active subscription, owners can track and mark payments, freelancers can view and download PDF invoices, and overdue invoices trigger automated alerts.

**Success criteria:** Monthly cron fires correctly and creates accurate invoices; PDFs render Arabic text with correct RTL layout; overdue detection flags invoices within 24 hours of due date; payment status updates propagate to both dashboards in real-time.

---

### Sprint 6 — Tasks T040–T047

---

#### T040 — Monthly Invoice Auto-Generation
**Type:** BE · **Priority:** P3 · **SP:** 8

**What to build:**  
A Laravel scheduled job that fires on the 1st of each month and creates one invoice per active subscription.

**Backend scope:**
- `GenerateMonthlyInvoices` artisan command: `php artisan invoices:generate-monthly`.
- Logic:
  1. Query all subscriptions where `status = 'active'` and `end_date IS NULL OR end_date >= today`.
  2. For each subscription, check if an invoice already exists for the current billing month (idempotency — prevents duplicate invoices if the command runs twice).
  3. Create `Invoice` record: `subscription_id`, `amount = subscription.monthly_price`, `due_date = 15th of current month`, `status = 'pending'`, `invoice_number = TAQAT-{YYYY}-{auto_increment}`.
  4. Dispatch `InvoiceCreated` notification to the member (queued).
- Schedule in `Console/Kernel.php` (or `bootstrap/app.php` in L11): `$schedule->command('invoices:generate-monthly')->monthlyOn(1, '08:00')` (8am on the 1st).
- Log the run to a `scheduler_logs` table or to Laravel's log with job counts for monitoring.

**Dependencies:** T007, T011.

---

#### T041 — Invoice Model & Status Management
**Type:** BE · **Priority:** P3 · **SP:** 3

**What to build:**  
The Invoice model with proper status transitions, scopes, and resource formatting.

**Backend scope:**
- Add query scopes to `Invoice` model: `scopePending()`, `scopePaid()`, `scopeOverdue()`, `scopeForWorkspace(int $workspaceId)`, `scopeForMember(int $memberId)`.
- `InvoiceResource`: returns all fields including computed `is_overdue` (whether `due_date < today && status != 'paid'`), formatted `invoice_number`, formatted `amount` with currency.
- `InvoiceController` (owner, auth):
  - `index`: paginated invoices for this workspace. Filter by `status`, `month`. Sorted by `due_date desc`.
  - `show`: single invoice with subscription + member info.
- `InvoiceController` (member, auth):
  - `index`: paginated invoices for this member.

**API:** `GET /api/workspace/invoices`, `GET /api/member/invoices`, `GET /api/workspace/invoices/:id`  
**Dependencies:** T040.

---

#### T042 — Manual Mark-as-Paid
**Type:** BE · **Priority:** P3 · **SP:** 3

**What to build:**  
Allow workspace owners to manually record that a member has paid an invoice (cash, bank transfer, etc.).

**Backend scope:**
- `InvoiceController@markPaid` (owner, auth):
  - Validate: invoice belongs to this workspace, `status != 'paid'`.
  - Accept optional `paid_at` timestamp (defaults to `now()`).
  - Update `invoice.status = 'paid'`, `invoice.paid_at = $paid_at`.
  - Dispatch `InvoicePaid` notification to member.
  - Bust the owner dashboard Redis cache.
  - Return updated `InvoiceResource`.
- Add a `sendReminder` action: dispatch a payment reminder notification to the member. Rate-limited to once per 24 hours per invoice.

**API:** `PUT /api/workspace/invoices/:id/pay`, `POST /api/workspace/invoices/:id/remind`  
**Dependencies:** T041.

---

#### T043 — Overdue Invoice Detection
**Type:** BE · **Priority:** P3 · **SP:** 3

**What to build:**  
A scheduled daily job that detects invoices past their due date and marks them as overdue, triggering notifications.

**Backend scope:**
- `MarkOverdueInvoices` artisan command: `php artisan invoices:mark-overdue`.
- Logic:
  1. Query all invoices where `status = 'pending'` and `due_date < today`.
  2. Batch-update their status to `overdue`.
  3. For each overdue invoice, dispatch `InvoiceOverdue` notification to the member AND to the workspace owner.
- Schedule: `$schedule->command('invoices:mark-overdue')->dailyAt('09:00')`.
- Add a grace period option (configurable via `.env`): `INVOICE_GRACE_DAYS=3` — only marks overdue after `due_date + grace_days`.

**Dependencies:** T041.

---

#### T044 — Invoice PDF Generation
**Type:** BE · **Priority:** P3 · **SP:** 5

**What to build:**  
Generate professional Arabic-language PDF invoices using DomPDF with a Blade template.

**Backend scope:**
- Install: `barryvdh/laravel-dompdf`.
- Configure DomPDF to embed the IBM Plex Sans Arabic font:
  - Download the TTF file.
  - Register in `config/dompdf.php` font directory.
  - This is **critical** — without embedded Arabic font, Arabic text renders as boxes.
- Create `resources/views/pdf/invoice.blade.php`:
  - Full RTL layout using HTML `dir="rtl"` and `text-align: right` in inline CSS.
  - Bilingual header: TAQAT logo text + Arabic company name.
  - Invoice metadata: number, date, due date.
  - Bill-to section: member name, workspace name, address.
  - Line items table: subscription period, seat number, monthly price. Numbers in LTR despite RTL layout (use `dir="ltr"` span on number cells).
  - Total amount (large, prominent).
  - Footer: "Thank you / شكراً لاشتراكك".
  - Status watermark: "مدفوع / PAID" in green diagonally overlaid if status = paid.
- `InvoiceController@downloadPdf`:
  - Generate PDF, upload to R2 at `invoices/{invoice_id}.pdf`.
  - Cache path in `invoice.invoice_pdf_path`.
  - On subsequent requests, return the cached R2 URL if already generated (avoid regenerating every download).
  - Stream PDF to client with `Content-Disposition: attachment`.

**API:** `GET /api/invoices/:id/pdf`  
**Dependencies:** T041, T025.

---

#### T045 — Owner Invoices Dashboard (Frontend)
**Type:** FE · **Priority:** P3 · **SP:** 8

**What to build:**  
The invoicing hub for workspace owners: view all invoices, track payment status, mark paid, send reminders, and download PDFs.

**Frontend scope:**
- Route: `app/(dashboard)/owner/invoices/`
- **Summary row:** Total outstanding (₪), Total overdue (₪), Paid this month (₪) — three stat tiles.
- **Invoices table:** Columns: Invoice #, Member Name, Period, Amount, Due Date, Status badge, Actions.
  - Status badges: `paid` (green), `pending` (amber), `overdue` (red pulsing).
  - Actions per row: Mark Paid, Send Reminder, Download PDF.
- **Filters:** Status, member name search, month range picker.
- **Mark Paid flow:** Inline confirmation in the table row (no modal needed). Optional date picker for `paid_at`. Optimistic update on the row.
- **"Issue Invoice" button:** Manual invoice creation form — member selector, amount, due date. For non-monthly billing edge cases.
- **"Export" button:** Downloads an Excel sheet of filtered invoices (T069).

**API:** `GET /api/workspace/invoices`, `PUT /api/workspace/invoices/:id/pay`, `GET /api/invoices/:id/pdf`  
**Dependencies:** T041, T042, T044.

---

#### T046 — Freelancer Invoices (Frontend)
**Type:** FE · **Priority:** P3 · **SP:** 5

**What to build:**  
The freelancer's invoice history — read-only view with PDF download.

**Frontend scope:**
- Route: `app/(dashboard)/freelancer/invoices/`
- Invoice list: all invoices with status badge, amount, due date, period.
- Status color coding consistent with owner dashboard.
- Download PDF button per row. Opens in new tab or triggers browser download.
- Overdue invoices: displayed at the top with a red alert banner encouraging the freelancer to contact the workspace.
- Paid invoices: checkmark icon, `paid_at` shown.

**API:** `GET /api/member/invoices`, `GET /api/invoices/:id/pdf`  
**Dependencies:** T041, T044.

---

#### T047 — Invoice Alert Cards (Both Dashboards)
**Type:** FE · **Priority:** P3 · **SP:** 2

**What to build:**  
Persistent callout components that appear on both dashboard home pages when overdue or pending invoices exist.

**Frontend scope:**
- `InvoiceAlert` component used in both `owner/page.tsx` and `freelancer/page.tsx`.
- Owner variant: "X فاتورة متأخرة / X overdue invoices" → links to invoices page with status=overdue filter pre-applied.
- Freelancer variant: "فاتورتك لشهر X متأخرة / Your invoice for month X is overdue" → links to invoices page.
- Styled with red border-left, amber background, warning icon. Dismissible per session (stored in `sessionStorage`).

**Dependencies:** T045, T046.

---

## Milestone M08 — Messaging, Notifications & Real-time

**Goal:** Workspace owners can message members individually or broadcast to all. All users receive instant in-app notifications. Real-time delivery via WebSocket.

---

### Sprint 7 — Tasks T048–T058

---

#### T048 — Messages Backend
**Type:** BE · **Priority:** P3 · **SP:** 3

**What to build:**  
The message persistence layer and controller.

**Backend scope:**
- `MessageController` (owner, auth):
  - `index`: list all message threads for this workspace (grouped by member). Return last message per thread + unread count.
  - `show`: all messages in a thread with a specific member.
  - `store` (direct): create a `message` record with `receiver_id = member_id`, `type = 'direct'`.
  - `broadcast`: create one `message` record with `receiver_id = null`, `type = 'broadcast'`. No fan-out in the database — the broadcast is a single record; recipients infer from workspace membership.
  - `markRead`: set `read_at = now()` for messages received by auth user.

**API:** `GET /api/workspace/messages`, `POST /api/workspace/messages`, `POST /api/workspace/messages/broadcast`  
**Dependencies:** T008.

---

#### T049 — Broadcast Message Fan-out
**Type:** BE · **Priority:** P3 · **SP:** 3

**What to build:**  
When an owner broadcasts a message, all active workspace members must receive an in-app notification. Handled via the queue to avoid blocking the request.

**Backend scope:**
- `BroadcastMessageJob`: Queued job that takes a `message_id` and the `workspace_id`. Queries all active subscriptions for the workspace. For each member, dispatches a `NewMessage` notification (DB channel + WebSocket push).
- The job is chunked in batches of 50 to avoid memory issues with large memberships.

**Dependencies:** T048, T050.

---

#### T050 — WebSocket Setup (Pusher/Soketi)
**Type:** DevOps · **Priority:** P3 · **SP:** 5

**What to build:**  
Real-time infrastructure connecting Laravel Broadcasting (server-side) to Next.js Pusher-js (client-side). Uses Pusher in development, can switch to self-hosted Soketi in production.

**Backend scope:**
- Set `BROADCAST_DRIVER=pusher` in `.env`.
- Configure `config/broadcasting.php` with Pusher credentials.
- Create channel auth route: `Route::post('/broadcasting/auth', ...)` in `api.php`. Protected by `auth:sanctum`.
- Private channels: `private-user.{userId}` for per-user notifications. Presence channel: `presence-workspace.{workspaceId}` for online member count (optional but useful for owner).

**Frontend scope (`lib/pusher.ts`):**
- Initialize Pusher client with `NEXT_PUBLIC_PUSHER_APP_KEY`, `NEXT_PUBLIC_PUSHER_CLUSTER`.
- `useChannel(channelName: string)` hook: subscribes to a Pusher channel on mount, unsubscribes on unmount.
- `usePrivateChannel(userId: string)` hook: authenticates and subscribes to `private-user.{userId}`. Uses the Laravel auth endpoint.
- `useEvent(channel, eventName, callback)` hook: binds an event listener to a channel.

**Dependencies:** T048.

---

#### T051 — Broadcast Events (Laravel)
**Type:** BE · **Priority:** P3 · **SP:** 5

**What to build:**  
Laravel event classes that get dispatched and pushed to Pusher when business events occur.

**Backend scope:**
Create the following event classes in `app/Events/`:
- `NewMessage`: dispatches on `private-user.{receiverId}` with message payload.
- `NewNotification`: dispatches on `private-user.{userId}` with notification payload.
- `InvoiceDue`: dispatches on `private-user.{memberId}` 5 days before invoice due date (triggered by a scheduled job).
- `SeatAssigned`: dispatches on `private-user.{memberId}` when a seat is assigned.
- `BookingStatusChanged`: dispatches on `private-user.{memberId}` when booking is approved/rejected.

Each event implements `ShouldBroadcast`, specifies its channel, and defines a `broadcastWith()` method returning only the data the frontend needs (avoid sending sensitive data over WebSocket).

**Dependencies:** T050.

---

#### T052 — Notifications Backend
**Type:** BE · **Priority:** P3 · **SP:** 3

**What to build:**  
A database-backed notification system that persists all notifications for retrieval, read-state tracking, and the notification center.

**Backend scope:**
- Use Laravel's built-in `notifications` table (run `php artisan notifications:table`).
- Create notification classes for all events listed in T051, each using both `DatabaseChannel` and `BroadcastChannel`.
- `NotificationController` (all authenticated users):
  - `index`: paginated notifications for auth user. Filter by `read_at IS NULL` (unread only). Return `unread_count` in response meta.
  - `markRead`: accept `ids[]` array or `all: true`. Updates `read_at = now()` for matching notifications.
  - `destroy`: delete a notification.

**API:** `GET /api/notifications`, `PUT /api/notifications/read`, `DELETE /api/notifications/:id`  
**Dependencies:** T051.

---

#### T053 — Notification Center (Frontend)
**Type:** FE · **Priority:** P3 · **SP:** 5

**What to build:**  
The in-app notification center: a bell icon in the top navigation that shows a live unread count badge and a dropdown panel of recent notifications.

**Frontend scope:**
- **Bell icon component (`components/layout/NotificationBell.tsx`):**
  - Unread count badge (amber, number). Pulses on new notification.
  - Click → opens `NotificationPanel` dropdown/popover.
  - Count is updated in real-time via `usePrivateChannel` (T050) listening for `NewNotification` events.
- **`NotificationPanel` component:**
  - Lists last 20 notifications (paginated with "Load more").
  - Each notification: icon (type-specific), title, body, timestamp (relative: "منذ 5 دقائق / 5 min ago"), read/unread background.
  - Click a notification: marks it read + navigates to the relevant page (invoice, seat, booking, etc.) based on notification type.
  - "Mark all as read" button at the top of the panel.
- Route: `app/(dashboard)/*/notifications/` — full page view of all notifications with filter (all/unread/invoices/messages/bookings).

**API:** `GET /api/notifications`, `PUT /api/notifications/read`  
**Dependencies:** T052.

---

#### T054 — Owner Messaging UI
**Type:** FE · **Priority:** P3 · **SP:** 5

**What to build:**  
The messaging interface for workspace owners: threaded view with individual members and a broadcast compose area.

**Frontend scope:**
- Route: `app/(dashboard)/owner/messages/`
- **Layout:** Left sidebar with member threads (sorted by last message time). Right panel shows selected thread messages.
- **Thread list:** Member avatar, name, last message preview (truncated 50 chars), timestamp, unread count badge.
- **Message thread:** Scrollable message history. Messages bubble-styled (owner messages right-aligned, member messages left-aligned — reversed in RTL). Each message shows sender, content, timestamp.
- Real-time: new messages appear instantly via `useChannel` listening to `NewMessage` event.
- **Compose:** Textarea at the bottom. Send on Enter (with Shift+Enter for newline).
- **Broadcast tab:** Separate "Broadcast" button opens a compose modal. "Send to all members" toggle. Preview shows recipient count before sending.

**API:** `GET /api/workspace/messages`, `POST /api/workspace/messages`  
**Dependencies:** T050, T048.

---

#### T055 & T056 — Announcements (Backend + Frontend)
**Type:** BE+FE · **Priority:** P3 · **SP:** 3+3

**What to build:**  
Owners can publish announcements (offers, info, alerts) to their workspace members. Announcements can be scheduled for future publishing and set to expire.

**Backend scope (T055):**
- `AnnouncementController` (owner): CRUD. `type` in `['offer', 'info', 'alert']`. `published_at = null` = draft. If `published_at` is in the past and `expires_at` is null or in the future: announcement is live.
- On publish (when `published_at` is set), dispatch `NewAnnouncement` notification to all active workspace members via `BroadcastMessageJob` pattern.
- Public endpoint: `GET /api/workspaces/:id/announcements` returns active non-expired announcements (shown on workspace detail page).

**Frontend scope (T056):**
- Route: `app/(dashboard)/owner/announcements/`
- Announcement list: cards with type badge (color-coded), title, published date, expiry, live/draft/expired status.
- "Create Announcement" → slide-in drawer form: type selector (icon badges: 📢 Offer, ℹ️ Info, ⚠️ Alert), title, body (textarea), schedule publish date (datetime picker, optional), expiry date (optional).
- "Publish Now" vs "Schedule" toggle.

**API:** `GET/POST/PUT/DELETE /api/workspace/announcements`  
**Dependencies:** T008, T052.

---

#### T057 & T058 — Reviews (Backend + Frontend)
**Type:** BE+FE · **Priority:** P3 · **SP:** 3+2

**What to build:**  
Freelancers can leave one review per workspace they have (or had) an active subscription with.

**Backend scope (T057):**
- `ReviewController`:
  - `store` (freelancer): validate that the user has (or had) a subscription to this workspace. Enforce `unique(member_id, workspace_id)` constraint. On create, recalculate `workspace.avg_rating` using `AVG(rating)` and update the denormalized column.
  - `index` (public): paginated reviews for a workspace.

**Frontend scope (T058):**
- On workspace detail page (T021), below the amenities section.
- Average rating display: large number + star row + total review count.
- Review cards: reviewer first name (last name masked for privacy), rating, comment, relative date.
- For authenticated freelancers with a subscription: "Write a Review" button → modal with star selector (1–5) + comment textarea.

**API:** `POST /api/reviews`, `GET /api/workspaces/:id/reviews`  
**Dependencies:** T007, T021.

---

---

# Phase 4 — Admin, Reports & Launch

> Completes the operator layer (Super Admin dashboard), adds reporting and data exports, and ships the platform to production.

---

## Milestone M10 — Super Admin Dashboard

**Goal:** The Super Admin has full visibility and control over the entire platform: approve/reject workspace registrations, manage all user accounts, configure commission rates, and broadcast system announcements.

---

### Sprint 8 — Tasks T059–T070

---

#### T059 — Admin: Workspace Approval
**Type:** BE · **Priority:** P4 · **SP:** 5

**What to build:**  
The admin's ability to approve, reject, or suspend any workspace registration.

**Backend scope:**
- `AdminWorkspaceController@updateStatus` (admin, auth):
  - Accept `status` (active/rejected/suspended) + `reason` (required for rejected/suspended).
  - On approve (`status = 'active'`): notify workspace owner with "Congratulations" email + access confirmation.
  - On reject: notify owner with reason. Workspace remains `rejected` — owner can contact support to appeal.
  - On suspend: notify owner. All active subscriptions within the workspace are paused (set `subscriptions.status = 'suspended'`).
- `AdminWorkspaceController@index`: all workspaces (any status), filterable by status, city, name. Includes owner info, submission date, document links.

**API:** `GET /api/admin/workspaces`, `PUT /api/admin/workspaces/:id/status`  
**Dependencies:** T023, T013.

---

#### T060 — Admin: User Management
**Type:** BE · **Priority:** P4 · **SP:** 3

**What to build:**  
Visibility and control over all user accounts on the platform.

**Backend scope:**
- `AdminUserController`:
  - `index`: all users, filterable by role, status, city. Include last login, subscription count (for freelancers), workspace name (for owners).
  - `show`: full user detail.
  - `updateStatus`: enable or disable any user account. Suspension cascades: if a freelancer is suspended, their active subscriptions are paused.
  - `impersonate` (optional/future): for debugging user issues.

**API:** `GET /api/admin/users`, `GET /api/admin/users/:id`, `PUT /api/admin/users/:id/status`  
**Dependencies:** T006, T013.

---

#### T061 — Admin: Platform Analytics
**Type:** BE · **Priority:** P4 · **SP:** 5

**What to build:**  
Aggregated platform-wide metrics for the admin dashboard.

**Backend scope:**
- `AnalyticsController@summary` (admin):
  ```json
  {
    "total_workspaces": { "active": 12, "pending": 3, "suspended": 1 },
    "total_users": { "freelancers": 87, "owners": 12 },
    "gross_revenue": { "this_month": 4200.00, "all_time": 28000.00 },
    "avg_occupancy_pct": 71.5,
    "new_signups_this_month": { "freelancers": 12, "owners": 2 },
    "revenue_chart": [ { "month": "2025-01", "amount": 3800 }, ... ],
    "occupancy_chart": [ { "month": "2025-01", "pct": 68.0 }, ... ],
    "top_workspaces": [ { "name": "...", "members": 18, "revenue": 900 }, ... ]
  }
  ```
- Cache aggressively: 30-minute TTL in Redis. Admin can trigger a manual cache bust.
- Date range filter: `?from=&to=` for custom period analytics.

**API:** `GET /api/admin/analytics`  
**Dependencies:** T040, T023.

---

#### T062 — Admin: Commission System
**Type:** BE · **Priority:** P4 · **SP:** 5

**What to build:**  
Commission configuration and tracking. TAQAT takes a percentage of each workspace's monthly revenue.

**Backend scope:**
- New `commission_configs` table: `rate_pct` (decimal), `effective_from` (date), `created_by`. Keep history — never update, only insert new rows (immutable config history).
- New `commission_transfers` table: `workspace_id`, `invoice_id`, `amount`, `rate_pct`, `period`, `status` (pending/transferred).
- When an invoice is marked as paid (T042): calculate commission = `invoice.amount * (current_rate_pct / 100)`. Create a `commission_transfer` record with `status = 'pending'`.
- `AdminCommissionController`:
  - `getConfig`: return current rate + history.
  - `setConfig`: insert new `commission_configs` record.
  - `listTransfers`: paginated commission transfer log.
  - `markTransferred`: bulk-update transfer records to `transferred`.

**API:** `GET/POST /api/admin/commissions`, `PUT /api/admin/commissions/:id/transfer`  
**Dependencies:** T042.

---

#### T063 — Admin: Global Announcements
**Type:** BE · **Priority:** P4 · **SP:** 3

**What to build:**  
Platform-wide announcement broadcast from the admin to all users.

**Backend scope:**
- `AdminAnnouncementController@broadcast` (admin):
  - Create an `announcement` record with `workspace_id = null` (signifying platform-wide).
  - Dispatch a queued job `BroadcastGlobalAnnouncement` that fans out a `NewNotification` to every active user on the platform.
  - This job must chunk users in batches of 100 and process asynchronously — it could be a large list.

**API:** `POST /api/admin/announcements`  
**Dependencies:** T052, T063.

---

#### T064–T068 — Admin Dashboard Frontend
**Type:** FE · **Priority:** P4 · **SP:** 5+5+3+3+2

**What to build:**  
The complete Super Admin UI across five pages.

**Frontend scope:**

**T064 — Analytics home (`app/(dashboard)/admin/page.tsx`):**
- Platform stat tiles: Total Workspaces, Total Freelancers, Gross Revenue, Avg Occupancy. Each with delta vs last month.
- Revenue area chart (Recharts), Occupancy line chart — both showing last 12 months.
- Top 5 workspaces table (name, members, revenue, occupancy %).
- New signups this week card.

**T065 — Workspace management (`app/(dashboard)/admin/workspaces/`):**
- Table: name, city, owner, submission date, seat count, status badge, actions (Approve / Reject / Suspend).
- "Pending" tab highlighted with count badge.
- Approve: confirmation modal with a congratulations message preview.
- Reject/Suspend: modal with required "reason" textarea.
- Row click → workspace detail panel (photos, info, documents download links, owner contact).

**T066 — User management (`app/(dashboard)/admin/users/`):**
- Tab bar: All / Freelancers / Owners.
- Table per role with relevant columns. Role-appropriate columns (Freelancers: specialty, subscription status; Owners: workspace name, workspace status).
- Enable/Disable toggle per row with confirmation.

**T067 — Finance (`app/(dashboard)/admin/finance/`):**
- Current commission rate card with "Edit Rate" button → inline number input + save.
- Commission transfers table: workspace, period, invoice amount, commission amount, status.
- Bulk "Mark as Transferred" for selected pending rows.

**T068 — Platform settings (`app/(dashboard)/admin/settings/`):**
- Commission rate (linked to T067).
- Default subscription plan options.
- Platform contact email, support phone.
- Language defaults.
- Maintenance mode toggle (with confirmation).

**Dependencies:** T059–T063.

---

## Milestone M11 — Reports, Exports & Analytics

**Goal:** Workspace owners can export their financial data to Excel and view detailed revenue/occupancy charts. Admins have a complete financial overview with commission breakdown.

---

#### T069 — Owner Reports & Excel Export
**Type:** FE+BE · **Priority:** P4 · **SP:** 5

**What to build:**  
Revenue and occupancy reports for workspace owners with Excel export.

**Backend scope:**
- `ReportController@ownerReport` (owner): accepts `?from=&to=` date range. Returns:
  - Revenue by month (sum of paid invoices).
  - Occupancy by month (avg occupied seats / total seats × 100).
  - Top members by total paid amount.
  - Invoice status breakdown (paid/pending/overdue counts).
- `ReportController@exportExcel` (owner): uses `Maatwebsite\Excel` to generate an XLSX:
  - Sheet 1: Monthly revenue table.
  - Sheet 2: Member invoices list.
  - Sheet 3: Occupancy summary.
  - Streams the file download. Filename: `taqat-report-{workspace_name}-{month}.xlsx`.

**Frontend scope:**
- Route: `app/(dashboard)/owner/reports/`
- Date range picker (month selector for from/to).
- Revenue chart (Recharts AreaChart, monthly bars).
- Occupancy gauge (Recharts RadialBarChart).
- Top members table.
- "Export to Excel" button → triggers file download.

**API:** `GET /api/workspace/reports`, `GET /api/workspace/reports/export`  
**Dependencies:** T040, T028.

---

#### T070 — Admin Financial Reports
**Type:** FE+BE · **Priority:** P4 · **SP:** 5

**What to build:**  
Platform-level financial reporting for the admin, broken down by workspace and showing commission performance.

**Backend scope:**
- `AdminReportController@financial`: by workspace: gross revenue, TAQAT commission earned, member count, avg invoice value. Supports date range filter.
- Returns a table suitable for both the UI display and an Excel export.

**Frontend scope:**
- Route: `app/(dashboard)/admin/reports/`
- Summary row: Total platform revenue, total TAQAT commission earned, total active subscriptions.
- Per-workspace breakdown table.
- Line chart: total platform revenue trend vs commission trend (two lines).
- "Export" button → XLSX.

**API:** `GET /api/admin/reports`  
**Dependencies:** T061, T062.

---

---

## Milestone M12 — Testing, Hardening & Production Launch

**Goal:** The platform survives real-world usage. All critical user flows have automated test coverage. The production environment is configured, monitored, and backed up.

**Success criteria:** Zero P1/P2 bugs in production; p95 API response time under 300ms; all Playwright E2E suites pass; Sentry receiving events; UptimeRobot monitoring live.

---

### Sprint 9 — Tasks T071–T080

---

#### T071 — PHPUnit: Auth Tests
**Type:** Testing · **Priority:** P4 · **SP:** 5

**Coverage targets:**
- `POST /api/auth/register` — Freelancer (valid, invalid email, weak password, duplicate email).
- `POST /api/auth/register` — Workspace owner (valid, missing documents, invalid file type).
- `POST /api/auth/login` — Valid credentials, wrong password, suspended account, unverified account.
- `POST /api/auth/logout` — Revokes token.
- Role gate middleware: freelancer hitting owner route → 403; owner hitting admin route → 403.
- Password reset: valid token, expired token.

**File:** `tests/Feature/Auth/RegisterTest.php`, `LoginTest.php`, `RoleGateTest.php`, `PasswordResetTest.php`

---

#### T072 — PHPUnit: Invoicing Tests
**Type:** Testing · **Priority:** P4 · **SP:** 5

**Coverage targets:**
- `GenerateMonthlyInvoices` command: creates correct number of invoices, is idempotent (running twice does not duplicate), skips cancelled subscriptions.
- `MarkOverdueInvoices` command: only marks invoices past due date, respects grace period.
- `InvoiceController@markPaid`: updates status, records `paid_at`, dispatches notification.
- `InvoiceController@downloadPdf`: returns PDF stream, correct Content-Type header.
- Commission creation: invoice marked paid → commission record created with correct amount.

**File:** `tests/Feature/Invoice/InvoiceGenerationTest.php`, `InvoicePaymentTest.php`, `OverdueTest.php`

---

#### T073 — PHPUnit: Booking & Subscription Tests
**Type:** Testing · **Priority:** P4 · **SP:** 5

**Coverage targets:**
- `BookingRequestController@store`: valid submission, duplicate prevention, booking to already-subscribed workspace.
- `BookingRequestController@update` (approve): subscription created, seat status updated, transaction integrity.
- `BookingRequestController@update` (reject): booking rejected, freelancer notified, no subscription created.
- Race condition: two owners approving the same seat simultaneously → second should get `409 Conflict`.
- Subscription cancel: `cancelled_at` set, seat unassigned, outstanding invoices remain.

**File:** `tests/Feature/Booking/BookingFlowTest.php`, `SubscriptionTest.php`

---

#### T074 — Playwright E2E: Booking Flow
**Type:** Testing · **Priority:** P4 · **SP:** 5

**What to test:**  
Full user journey: freelancer registration → discover workspace → submit booking → owner approves → freelancer sees active subscription.

**Test steps:**
1. Register as a new freelancer (fill 3-step form, upload mock ID).
2. Navigate to Explore. Apply city filter.
3. Open workspace detail. Click "Request Booking". Fill message. Submit.
4. Log out. Log in as the workspace owner.
5. Navigate to Booking Requests. See the new request.
6. Click Approve. Select a seat from the seat picker. Confirm.
7. Log out. Log in as the freelancer.
8. Verify dashboard shows active subscription + assigned seat number.

**File:** `e2e/booking-flow.spec.ts`  
**Tools:** Playwright + `@playwright/test`. Use fixture-based auth to skip re-running login for already-authenticated states.

---

#### T075 — Playwright E2E: Invoice Flow
**Type:** Testing · **Priority:** P4 · **SP:** 5

**What to test:**  
Invoice generation → mark paid → freelancer downloads PDF.

**Test steps:**
1. (Seeded state) Active subscription exists.
2. As owner: navigate to Invoices. See the pending invoice. Click "Mark Paid".
3. Verify invoice status changes to "Paid" in the table.
4. Log in as the freelancer. Navigate to My Invoices.
5. Verify the invoice shows as "Paid" with `paid_at` date.
6. Click "Download PDF". Verify file downloads (check filename + Content-Type).
7. Verify notification appeared in freelancer's notification center ("Invoice paid").

**File:** `e2e/invoice-flow.spec.ts`

---

#### T076 — Load Testing (k6)
**Type:** Testing · **Priority:** P4 · **SP:** 3

**What to test:**  
The two most-trafficked API endpoints under concurrent load.

**Test scenarios:**
- `GET /api/workspaces` with filter params: 100 virtual users, 1-minute ramp-up, 5-minute sustained. Target p95 < 200ms.
- `GET /api/member/dashboard`: 50 authenticated VUs (inject tokens via k6 `setup()` function). Target p95 < 300ms.
- `POST /api/booking-requests`: 20 VUs sending concurrent booking requests. Verify no 500 errors. Race condition test for seat assignment.

**Pass criteria:** 0% error rate, p95 latency under target, no degradation after 3 minutes sustained.

**File:** `k6/load-test.js`  
**Run:** `k6 run --vus 100 --duration 5m k6/load-test.js`

---

#### T077 — Production Server Setup
**Type:** DevOps · **Priority:** P4 · **SP:** 5

**What to build:**  
Production environment on DigitalOcean. Two server blocks: `api.taqat.space` for Laravel, `taqat.space` for Next.js.

**Steps:**
- Provision a DigitalOcean Droplet: Ubuntu 24.04 LTS, 4GB RAM / 2 vCPU ($24/mo). Enable automated backups.
- Install: Nginx, PHP 8.3-FPM, PostgreSQL 15, Redis 7, Node 20, Composer, PM2.
- Configure Nginx:
  - `taqat.space`: `proxy_pass http://localhost:3000` (Next.js).
  - `api.taqat.space`: FastCGI to PHP-FPM for Laravel. Set `client_max_body_size 10M` for file uploads.
- SSL: Certbot with Let's Encrypt for both domains. Auto-renewal cron.
- Laravel production checklist: `APP_ENV=production`, `APP_DEBUG=false`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
- Next.js: `npm run build`, run via PM2: `pm2 start npm --name "taqat-frontend" -- start`.
- Supervisor config for Laravel queue workers: 4 worker processes, auto-restart on failure.
- Cron entry: `* * * * * cd /var/www/taqat-api && php artisan schedule:run >> /dev/null 2>&1`.

---

#### T078 — Environment Hardening & Backups
**Type:** DevOps · **Priority:** P4 · **SP:** 3

**What to build:**  
Secrets management and automated database backup.

**Steps:**
- All secrets in `/etc/environment` on the server (not in git). Deployment script reads from there.
- PostgreSQL daily backup cron: `pg_dump taqat_production | gzip > /backups/db-$(date +%Y%m%d).sql.gz`. Upload to R2 bucket `taqat-backups`. Retain 30 days.
- R2 bucket `taqat-backups` — private, versioning enabled.
- Test restore procedure: document and verify that a full restore from backup works before launch.
- Firewall (UFW): allow only 22 (SSH, restricted to your IP), 80, and 443. Block everything else.

---

#### T079 — Monitoring Setup
**Type:** DevOps · **Priority:** P4 · **SP:** 3

**What to build:**  
Error tracking and uptime monitoring before any production traffic.

**Steps:**
- **Sentry (Laravel):** Install `sentry/sentry-laravel`. Configure DSN. Capture exceptions in `bootstrap/app.php`. Verify a test error appears in the Sentry dashboard.
- **Sentry (Next.js):** Install `@sentry/nextjs`. Run setup wizard. Capture both client-side and server-side errors.
- **UptimeRobot (or BetterUptime):** Add monitors for:
  - `https://taqat.space` — HTTP 200 check, 5-minute interval.
  - `https://api.taqat.space/api/health` — JSON response check `{ status: "ok" }`.
  - Alert to team Slack channel + founder's phone.
- **Sentry alerts:** Configure alerts for any unhandled exception reaching Sentry to notify via email + Slack.

---

#### T080 — Staging Deploy & Launch Checklist
**Type:** DevOps · **Priority:** P4 · **SP:** 3

**What to build:**  
Final validation gate before pointing DNS to production.

**Checklist to complete before go-live:**
- [ ] Staging environment identical to prod (same Droplet size, same env vars, real data from anonymized seed).
- [ ] All 5 Playwright E2E suites pass on staging.
- [ ] PHPUnit test suite passes on staging database.
- [ ] k6 load test passes against staging API.
- [ ] Invoice PDF renders correctly with Arabic text (spot-check 3 invoices).
- [ ] RTL layout correct on Home, Owner Dashboard, and Freelancer Dashboard on mobile.
- [ ] Email delivery confirmed (Mailgun test send to real address).
- [ ] WebSocket connection established in browser (check Pusher dashboard for connected clients).
- [ ] Admin can approve a workspace on staging.
- [ ] Sentry receiving test error from both FE and BE.
- [ ] UptimeRobot monitors green.
- [ ] Backup cron job has run once and file exists in R2.
- [ ] DNS TTL lowered to 60s before cutover (revert to 3600 after).
- [ ] Rollback plan documented: git tag of known-good release + deployment script to re-deploy it.

---

---

# API Contract Index

Quick reference for all 41 API endpoints.

| # | Method | Endpoint | Auth | Role | Sprint |
|---|--------|----------|------|------|--------|
| 1 | POST | `/api/auth/register` | — | All | S2 |
| 2 | POST | `/api/auth/login` | — | All | S2 |
| 3 | POST | `/api/auth/logout` | Bearer | All | S2 |
| 4 | POST | `/api/auth/forgot-password` | — | All | S2 |
| 5 | POST | `/api/auth/reset-password` | — | All | S2 |
| 6 | GET | `/api/workspaces` | — | Public | S3 |
| 7 | GET | `/api/workspaces/:id` | — | Public | S3 |
| 8 | GET | `/api/workspaces/:id/reviews` | — | Public | S7 |
| 9 | POST | `/api/booking-requests` | Bearer | Freelancer | S5 |
| 10 | GET | `/api/workspace/booking-requests` | Bearer | Owner | S4 |
| 11 | PUT | `/api/workspace/booking-requests/:id` | Bearer | Owner | S5 |
| 12 | GET | `/api/workspace/dashboard` | Bearer | Owner | S4 |
| 13 | GET | `/api/workspace/members` | Bearer | Owner | S4 |
| 14 | PUT | `/api/workspace/members/:id/status` | Bearer | Owner | S4 |
| 15 | GET | `/api/workspaces/:id/seats` | Bearer | Owner/Public | S4 |
| 16 | PUT | `/api/seats/:id/assign` | Bearer | Owner | S4 |
| 17 | POST | `/api/workspace/invoices` | Bearer | Owner | S6 |
| 18 | GET | `/api/workspace/invoices` | Bearer | Owner | S6 |
| 19 | PUT | `/api/workspace/invoices/:id/pay` | Bearer | Owner | S6 |
| 20 | GET | `/api/invoices/:id/pdf` | Bearer | Owner/Freelancer | S6 |
| 21 | POST | `/api/workspace/messages` | Bearer | Owner | S7 |
| 22 | POST | `/api/workspace/announcements` | Bearer | Owner | S7 |
| 23 | GET | `/api/workspace/reports` | Bearer | Owner | S8 |
| 24 | GET | `/api/workspace/reports/export` | Bearer | Owner | S8 |
| 25 | POST | `/api/workspace/packages` | Bearer | Owner | S5 |
| 26 | PUT | `/api/workspace/packages/:id/assign` | Bearer | Owner | S5 |
| 27 | GET | `/api/member/dashboard` | Bearer | Freelancer | S4 |
| 28 | GET | `/api/member/subscriptions` | Bearer | Freelancer | S5 |
| 29 | GET | `/api/member/invoices` | Bearer | Freelancer | S6 |
| 30 | PUT | `/api/member/profile` | Bearer | Freelancer | S5 |
| 31 | POST | `/api/reviews` | Bearer | Freelancer | S7 |
| 32 | GET | `/api/notifications` | Bearer | All | S7 |
| 33 | PUT | `/api/notifications/read` | Bearer | All | S7 |
| 34 | GET | `/api/admin/analytics` | Bearer | Admin | S8 |
| 35 | GET | `/api/admin/workspaces` | Bearer | Admin | S8 |
| 36 | PUT | `/api/admin/workspaces/:id/status` | Bearer | Admin | S8 |
| 37 | GET | `/api/admin/users` | Bearer | Admin | S8 |
| 38 | PUT | `/api/admin/users/:id/status` | Bearer | Admin | S8 |
| 39 | GET | `/api/admin/commissions` | Bearer | Admin | S8 |
| 40 | POST | `/api/admin/commissions` | Bearer | Admin | S8 |
| 41 | POST | `/api/admin/announcements` | Bearer | Admin | S8 |

---

*Document generated for internal development use only — TAQAT.space © 2025*
