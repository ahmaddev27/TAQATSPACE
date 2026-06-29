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

// Remind members 3 days before their subscription expires.
Schedule::command('subscriptions:notify-expiring')->dailyAt('08:30');

// Drain queued jobs (emails, SMS broadcasts, push + DB notifications) every
// minute. The queue connection is `database` and shared hosting has no
// persistent worker, so we process the backlog from the scheduler instead:
// run until the queue is empty, cap the runtime so it exits before the next
// tick, and never overlap with a still-running drain.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
