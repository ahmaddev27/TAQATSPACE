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

// Remind members 3 days before their subscription expires.
Schedule::command('subscriptions:notify-expiring')->dailyAt('08:30');
