# Cron & Notifications — Server Setup

This guide explains how to make **scheduled tasks** (monthly invoices, overdue
marking, subscription‑expiry reminders) and **notifications** (in‑app, email, and
FCM push) actually run on the server.

> **The single most important fact:** every notification in this app is **queued**
> (`implements ShouldQueue`, `QUEUE_CONNECTION=database`). A notification is **not**
> delivered when it is triggered — it is written to the `jobs` table and delivered
> **only when a queue worker processes it**. **If no queue worker is running, no
> notification is ever delivered** (no in‑app row, no email, no push) — the jobs
> just pile up in the `jobs` table.

There are **two** independent pieces, and you need **both**:

| Piece | Command | Purpose | Cadence |
|---|---|---|---|
| **Scheduler** | `php artisan schedule:run` | Fires the time‑based commands (invoices, reminders) | every **1 min** (cron) |
| **Queue worker** | `php artisan queue:work` | Delivers **all** notifications + any queued job | continuous (or 1‑min cron) |

Paths used below (from the repo's GitHub Actions variables):

- **Staging API path:** `/home/space/public_html/api.staging.taqat.space`
- **Production API path:** `/home/space/public_html/api.taqat.space`

Replace `php` with the absolute PHP 8.3 binary if the default `php` on the host is
an older version — on cPanel it is usually something like
`/usr/local/bin/ea-php83` or `/opt/cpanel/ea-php83/root/usr/bin/php`.

---

## 1. The scheduler (cron) — time‑based tasks

The app schedules three commands (see `backend/routes/console.php`):

| Command | When | What it does |
|---|---|---|
| `invoices:generate-monthly` | 1st of month, 08:00 | Generates that month's invoices |
| `invoices:mark-overdue` | daily, 09:00 | Marks past‑due invoices overdue + notifies |
| `subscriptions:notify-expiring` | daily, 08:30 | Reminds members 3 days before expiry |

Laravel needs **one** cron entry that ticks every minute; Laravel itself decides
which command is due. In **cPanel → Cron Jobs**, add:

```cron
* * * * * cd /home/space/public_html/api.staging.taqat.space && php artisan schedule:run >> /dev/null 2>&1
```

For production, add the same line with the production path
(`/home/space/public_html/api.taqat.space`).

Verify the scheduler sees the commands:

```bash
cd /home/space/public_html/api.staging.taqat.space
php artisan schedule:list
```

---

## 2. The queue worker — REQUIRED for notifications

Because every notification is queued, **nothing is delivered without a worker.**

### Option A — cron‑based worker (recommended on shared cPanel)

cPanel rarely has Supervisor, so run a **bounded** worker every minute. It drains
the pending jobs, then exits before the next tick (so runs never overlap):

```cron
* * * * * cd /home/space/public_html/api.staging.taqat.space && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> storage/logs/worker.log 2>&1
```

- `--stop-when-empty` — exit once the queue is drained (don't linger).
- `--max-time=55` — hard‑stop before the next minute (no overlap).
- `--tries=3` — retry a failing job up to 3 times before moving it to `failed_jobs`.

Add the same line with the production path for prod.

### Option B — persistent worker (if the host allows long‑running processes)

If you have SSH and the host permits a long‑lived process (or Supervisor):

```bash
cd /home/space/public_html/api.staging.taqat.space
nohup php artisan queue:work --tries=3 --max-time=3600 >> storage/logs/worker.log 2>&1 &
```

With Supervisor (preferred when available), a typical program block:

```ini
[program:taqat-staging-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/space/public_html/api.staging.taqat.space/artisan queue:work --tries=3 --sleep=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
user=space
redirect_stderr=true
stdout_logfile=/home/space/public_html/api.staging.taqat.space/storage/logs/worker.log
stopwaitsecs=60
```

### Important: restart the worker after every deploy

The deploy workflow already runs `php artisan queue:restart`. A **persistent**
worker (Option B) must be running for that signal to take effect — otherwise the
worker keeps executing **old** code after a deploy. The cron‑based worker
(Option A) picks up new code automatically because it starts fresh each minute.

---

## 3. Email delivery (`MAIL_*`)

Notifications also send email (`via = ['database', 'mail']`). In `.env`,
`MAIL_MAILER=log` only **writes** emails to the log — it does **not** send them.
For real delivery set a real transport in the server `.env`, e.g.:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@taqat.space"
MAIL_FROM_NAME="TAQAT.space"
```

After editing `.env`, refresh the cached config:

```bash
php artisan config:clear && php artisan config:cache
```

---

## 4. FCM push (mobile/web push)

In‑app + email work with just the queue worker. **Push** additionally needs the
Firebase **service‑account** credentials configured server‑side (used by the
`PushNotificationToDevices` listener via `FirebaseService`). Without them, push is
silently skipped while in‑app + email still work. Ensure the Firebase
service‑account env/credentials are present in the server `.env`.

---

## 5. Verify it works

```bash
cd /home/space/public_html/api.staging.taqat.space

# 1) Is the worker draining jobs? Pending jobs should trend to ~0.
php artisan tinker --execute="echo 'pending=' . DB::table('jobs')->count() . PHP_EOL;"

# 2) Any failures? If non‑zero, inspect them.
php artisan queue:failed

# 3) Process exactly one job by hand and watch the output.
php artisan queue:work --once -v

# 4) Confirm the schedule is registered.
php artisan schedule:list
```

**Healthy signs:** `jobs` count stays near 0, `queue:failed` is empty, and an
in‑app notification appears (bell badge) shortly after a triggering action
(e.g. a freelancer submits a booking request → the owner gets notified).

---

## 6. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| No notifications at all; `jobs` table grows | Queue worker not running | Add the Option A cron (or start Option B) |
| In‑app works, **email** doesn't | `MAIL_MAILER=log` or bad SMTP | Set real SMTP (§3), `config:cache` |
| In‑app + email work, **push** doesn't | Firebase service‑account missing | Configure server‑side Firebase creds (§4) |
| Scheduled invoices/reminders never fire | No `schedule:run` cron | Add the §1 cron |
| Jobs land in `failed_jobs` | Job threw (mail/FCM/DB) | `php artisan queue:failed` → fix → `queue:retry all` |
| Worker runs old code after deploy | Persistent worker not restarted | Ensure `queue:restart` reaches a live worker, or use Option A |

---

## Quick start (staging, copy‑paste into cPanel → Cron Jobs)

```cron
# Laravel scheduler — fires due commands
* * * * * cd /home/space/public_html/api.staging.taqat.space && php artisan schedule:run >> /dev/null 2>&1

# Queue worker — delivers all notifications
* * * * * cd /home/space/public_html/api.staging.taqat.space && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> storage/logs/worker.log 2>&1
```

Then set real `MAIL_*` (+ Firebase creds for push) in the server `.env` and run
`php artisan config:cache`.
