# Testing on staging

Work is exercised on staging, with dummy data, in a real browser, for every role.
Two pieces make that repeatable: a command that creates the dummy data, and a
Playwright suite that walks the site with it.

## 1. Demo data

`php artisan demo:seed [--fresh]` (backend, `app/Services/Demo`).

| What | Detail |
| --- | --- |
| Accounts | Every address ends in `@demo.taqat.test` (a `.test` domain cannot be delivered). |
| Workspaces | Every slug starts with `demo-` (`demo-almanara`, `demo-ibdaa`, `demo-stepup`, and `demo-throwaway`, which only the reset test touches). |
| Password | Read from the `DEMO_PASSWORD` environment variable. The command refuses to run without it and never prints it. |
| `--fresh` | Deletes only rows owned by demo accounts or demo workspaces, then seeds again. Other staging data is untouched. Without `--fresh`, an existing demo set is left as it is. |

Accounts (all with the same password; the local part is the key used by the tests):

| Role | Accounts |
| --- | --- |
| Platform admin | `admin` (every permission), `admin.limited` (no `approve_requests`, no `manage_admins`) |
| Workspace owner | `owner1` (Al-Manara), `owner2` (Ibdaa), `owner.stepup` (enrols two-factor in the checks), `owner.throwaway` |
| Staff (Al-Manara) | `staff.cashier`, `staff.hospitality`, `staff.finance` |
| Freelancer | `freelancer1` ... `freelancer10` |

The data covers seats, packages, bookings (pending, approved, rejected),
subscriptions in every status, invoices in every status (including a partial
payment), expenses, POS products and orders, and audit entries. One class per
area lives in `app/Services/Demo/Steps`; a later milestone adds its own class
and lists it in `config/demo.php`.

### Modules M2 to M5

The flags split the tenants: `owner1` (Al-Manara) and `owner.stepup` have the
`network` and `finance_v2` flags on, `owner2` and `owner.throwaway` have them off,
so both paths run every time (`ModulesStep`, through `FeatureFlagService`, which
also seeds the expense categories that `finance_v2` brings).

| Step | What it creates (Al-Manara unless noted) |
| --- | --- |
| `NetworkStep` | Two routers: "Main router" (the one the integration page shows; an older script version so the update check has something to offer) and "Sync probe router". Hotspot accounts for two subscribers, queued router commands, logins, a logout and a snapshot pushed through the real sync handler with the router wire format, then processed into devices, sessions and attendance. |
| `StationsStep` | A second branch, stations with zone, kind, capacity and a private office, one member with a complete donor profile and the others incomplete. |
| `FinanceV2Step` | Catalog categories and items (one category archived), one plan per pricing method, financial terms on a subscription, open and voided unbilled charges, invoices in draft, issued and void, a cash receipt with a partial allocation, an uncollected cheque, a voided expense. All through the finance services. |
| `HourPackagesStep` | Two platform packages (`DEMO-` codes) and a purchase in `requested`. |
| `ModulesStep` | Also gives `owner.stepup` two members so invoices and receipts have customers. |

The probe router's key is never stored or printed: it is derived from the demo
password (`DemoMarker::routerKeyFor`, HMAC-SHA256), and the suite computes the
same value from `E2E_DEMO_PASSWORD`. Hour packages belong to the platform, so the
cleaner removes the ones whose code starts with `DEMO-` and that have no purchase
left.

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
  links; the workspace reset on the throwaway workspace only. The module switch
  is exercised on `gsg`, because `network` and `finance_v2` are on from the seed.
- Page walk with flags: an account walks the pages its workspace's flags open
  (`ENABLED_FLAGS` in `support/accounts.ts`): the integration page, its
  diagnostics page, and the catalog, plans and receipts pages for `owner1`.
- Flags off (`owner2`): the gated menu entries are absent, their routes answer
  404, finance v2 endpoints answer 404 and network endpoints 403, the feature map
  says everything is off, and an expense delete still removes the row.
- M2 (`flows-network.spec.ts`): the integration page shows the router; an install
  command is issued in the browser and a new one replaces it; the install link
  works once; a script update is queued once and not repeated; diagnostics returns
  the log and runs the deep check in the API and the browser; keys never appear in
  the router list. The sync endpoint is called over HTTP with `text/plain` in the
  documented format: the reply starts with `CWS-SYNC <nonce>`, a repeated batch
  gets the identical reply, a wrong or missing key gets 401, a malformed line or a
  missing `H` line gets 422.
- M3 (same file): the sync shows up in the router's request log and the router is
  online. There is no UI for the data yet and the queue is not waited on.
- M4 (`flows-stations.spec.ts`): the four station tabs; bulk add, the duplicate and
  capacity refusals; kind, zone and privacy kept; branch create and delete with
  the default and has-stations guards, and another workspace's owner refused; the
  incomplete filter, the donor fields saving and completeness following, invalid
  values and foreign members refused.
- M5 (`flows-finance.spec.ts`): catalog item and category create, edit, archive;
  plans of the flat, metered and bundle kinds, create, edit, archive and their
  refusals; subscription financial terms; unbilled charge record and void, and the
  charge going to an invoice and back; invoice draft, edit, issue, void, tax, and
  a draft or void invoice refusing payment; a receipt across two invoices, a
  partial allocation finished later, over-allocation refused, a cheque paying
  when it clears and putting the invoice back when it bounces; expense void; the
  POS product edit keeping its price with the flag off and the item winning with
  it on.
- Step-up (`flows-stepup.spec.ts`, on `owner.stepup`): the account enrols
  two-factor through the API, several access tokens are taken before that and
  stay stale until verified (the state is per token), so key rotation, invoice
  issue and receipt void are each refused with `two_factor_required`, then work
  after a recovery code is used; an owner without two-factor is refused with
  `two_factor_enrollment_required`; a plan of another workspace cannot be attached.
- Hour packages (`flows-hours.spec.ts`): the admin creates, edits and archives a
  package; activates the seeded request with a receipt, the second activation is
  refused (409) and the active count rises by one; owners and members are refused.
- Staff (`flows-staff-finance.spec.ts`): the cashier may read receipts and is
  refused the catalog, plans, unbilled charges, issue, void, routers and the
  platform packages at the API.

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
- The `/owner/network` and `/owner/gsg` menu entries: the sidebar links to them as
  soon as the flag is on, but the pages do not exist yet (they answer 404). They
  are left out of the walk (`PAGES_NOT_BUILT_YET` in `support/walk.ts`).
- The realtime chat pages, whose presence depends on staging configuration.
- M3 data in a screen (there is none yet) and the queue: nothing waits for the
  background job, the seed runs the batch processor itself.
- Click-through of the finance screens (catalog, plans, receipts, the member
  profile wizard, the hour package screen): the flows run against the API, and the
  pages are covered by the walk. Only the integration, diagnostics, stations and
  hour package pages have browser steps.
- The cashier and finance staff in the browser, beyond the page walk.
- There is no admin screen for the module switch yet, so the test uses the admin API.
