<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class RemindOverdueInvoices extends Command
{
    /** @var string */
    protected $signature = 'invoices:remind-overdue {--cooldown=7 : Min days between reminders for the same invoice}';

    /** @var string */
    protected $description = 'Re-remind members about invoices still unpaid past their due date, throttled per invoice.';

    public function handle(InvoiceService $invoices): int
    {
        $cooldown = (int) $this->option('cooldown');

        $this->info("Reminding members of overdue invoices (cooldown: {$cooldown} days)...");

        $sent = $invoices->remindOverdue($cooldown);

        $this->info("Sent {$sent} overdue reminder(s).");

        return self::SUCCESS;
    }
}
