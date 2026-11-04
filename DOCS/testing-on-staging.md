# Testing on staging

Work is exercised on staging, with dummy data, in a real browser, for every role.
Two pieces make that repeatable: a command that creates the dummy data, and a
Playwright suite that walks the site with it.

## 1. Demo data

`php artisan demo:seed [--fresh]` (backend, `app/Services/Demo`).

| What | Detail |
| --- | --- |
| Accounts | Every address ends in `@demo.taqat.test` (a `.test` domain cannot be delivered). |
| Workspaces | Every slug starts with `demo-` (`demo-almanara`, `demo-ibdaa`, and `demo-throwaway`, which only the reset test touches). |
| Password | Read from the `DEMO_PASSWORD` environment variable. The command refuses to run without it and never prints it. |
| `--fresh` | Deletes only rows owned by demo accounts or demo workspaces, then seeds again. Other staging data is untouched. Without `--fresh`, an existing demo set is left as it is. |

Accounts (all with the same password; the local part is the key used by the tests):

| Role | Accounts |
| --- | --- |
| Platform admin | `admin` (every permission), `admin.limited` (no `approve_requests`, no `manage_admins`) |
| Workspace owner | `owner1` (Al-Manara), `owner2` (Ibdaa), `owner.throwaway` |
| Staff (Al-Manara) | `staff.cashier`, `staff.hospitality`, `staff.finance` |
| Freelancer | `freelancer1` ... `freelancer10` |

The data covers seats, packages, bookings (pending, approved, rejected),
subscriptions in every status, invoices in every status (including a partial
payment), expenses, POS products and orders, and audit entries. One class per
area lives in `app/Services/Demo/Steps`; a later milestone adds its own class
and lists it in `config/demo.php`.

### Safety

The repository is public and the command creates accounts with a known password.

- It does not trust `APP_ENV`. It runs only when the environment is `local` or
  `testing`, or when the host of `APP_URL` contains `staging`.
- It refuses `taqat.space`, `www.taqat.space` and `api.taqat.space` whatever
  `APP_ENV` says, and refuses an unset `APP_URL`.
- Demo accounts never cause an outgoing email, SMS or push: the mail channel,
  the push listener, the broadcast job and the internet-credentials delivery all
  skip demo addresses (`User::isDemo()`), and demo accounts have no phone number.

## 2. Seeding staging

GitHub Actions, "Seed Staging Demo Data" (manual). It connects with the same SSH
secrets as the staging deploy, uses only `STAGING_API_PATH`, and passes the
`STAGING_DEMO_PASSWORD` secret on stdin. Choose whether to run with `--fresh`.

One-time setup: create the repository secret `STAGING_DEMO_PASSWORD`.

## 3. The browser suite

`frontend/tests-e2e`, workflow "E2E Staging". It runs after each successful
"Deploy Staging" and on demand. It first runs the seed workflow with `--fresh`,
so the tests always start from the same data. It runs on GitHub's machines and
deploys nothing.

What it checks:

- Every demo role can log in through `/api/auth/login` (the existing route).
- Every page in each role's menu loads, in Arabic and English. A page fails on an
  HTTP 4xx/5xx, an uncaught browser error, a failed call to the API, or an error
  screen.
- Each role is sent back to its own area from other roles' pages, and the API
  answers 403 for the same.
- M1 flows: two-factor enrolment and login with a code; a recovery code works
  once; invalid IBAN rejected and general settings saved; an operations change is
  held for approval, not applied, then applied after approval; rejection needs a
  reason; staff invitation and narrowing permissions; the audit log shows the
  actions and only its own workspace; the module switch (network) from the admin
  shows and hides the owner's menu item; keyboard shortcuts; the domain tab
  links; the workspace reset on the throwaway workspace only.

### Running it by hand

```bash
cd frontend
export E2E_BASE_URL=https://staging.taqat.space
export E2E_API_URL=https://api.staging.taqat.space/api
export E2E_DEMO_PASSWORD=...          # the demo password; never commit it
npx playwright test --project=chromium
```

`npx playwright test --list` shows the suite without running it.

### Reading the results

The job log lists each test. On a failure, a screenshot is attached as the
`e2e-failure-screenshots` artifact (kept 7 days). The repository is public, so
artifacts are public: the suite records screenshots of failures only, never
traces or videos (they could capture a password or a token), and it uses demo
data only. The two-factor secret and recovery codes shown on screen belong to the
throwaway demo owner.

## 4. Not covered

- The real SSO login screen (it needs a test account on the identity provider).
- Real delivery of email, SMS and push.
- Real routers (covered by task 2.12).
- Pages behind a module flag (network, GSG) and the realtime chat pages, whose
  presence depends on staging configuration. The module switch is tested through
  the menu entry it controls.
- There is no admin screen for the module switch yet, so the test uses the admin API.
