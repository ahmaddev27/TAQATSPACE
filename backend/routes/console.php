<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Invoicing schedule (M07 — T040, T043).
Schedule::command('invoices:generate-monthly')->monthlyOn(1, '08:00');
Schedule::command('invoices:mark-overdue')->dailyAt('09:00');

// Chase invoices that stay unpaid past due — markOverdue only notifies once at
// the transition, so re-remind weekly (throttled per invoice in the service).
Schedule::command('invoices:remind-overdue')->weeklyOn(1, '09:30');

// End subscriptions past their end date (end-date based; grace days come in 4.7).
Schedule::command('subscriptions:expire')->dailyAt('00:30');

// Remind members 3 days before their subscription expires.
Schedule::command('subscriptions:notify-expiring')->dailyAt('08:30');

// Router API request logs are only kept for 14 days.
Schedule::command('router-logs:prune')->dailyAt('03:00');

// Retention policy (config/retention.php): processed raw events, assembled
// snapshot chunks and router logs. Replaces the scheduled router-snapshots:prune,
// which would also delete chunks of snapshots that are not assembled yet.
Schedule::command('network:prune')->dailyAt('03:15');

// Late-item fallback for the ingest queue: /sync dispatches ProcessRouterBatch
// itself; this catches runs that were lost or failed.
Schedule::command('router-data:process-pending')->everyMinute()->withoutOverlapping();

// Open sessions of routers that stopped syncing time out here (config/network.php).
Schedule::command('sessions:close-idle')->everyFiveMinutes()->withoutOverlapping();

// A router silent past the threshold gets an open gap, so its days are flagged instead of read as absence.
Schedule::command('routers:detect-gaps')->everyMinute()->withoutOverlapping();

// Daily attendance: days whose sessions changed are rebuilt every five minutes;
// the nightly pass re-covers the last two local days of every workspace as a safety net.
Schedule::command('attendance:rebuild')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('attendance:rebuild --recent')->dailyAt('01:15')->withoutOverlapping();

// Router commands nobody acknowledged before their ttl must stop being resent.
Schedule::command('router-commands:expire')->everyFiveMinutes();

// Drain queued jobs (emails, SMS broadcasts, push + DB notifications) every
// minute. The queue connection is `database` and shared hosting has no
// persistent worker, so we process the backlog from the scheduler instead:
// run until the queue is empty, cap the runtime so it exits before the next
// tick, and never overlap with a still-running drain.
Schedule::command('queue:work --queue=ingest,default --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Statistics follow the nightly attendance pass, and recalculations (the owner's
// button, stats:recalculate) run on their own queue and worker: one can take
// minutes and must not hold up the ingest drain above. The mutex expires after
// 30 minutes so a killed worker cannot block the queue for the default day.
Schedule::command('stats:recalculate')->dailyAt('01:45')->withoutOverlapping();
// Scores are also recomputed by every recalculation above; this pass applies changed
// weights and moves the "days without activity" forward even on a quiet night.
Schedule::command('scores:compute')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('queue:work --queue=heavy --stop-when-empty --max-time=55 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(30);
