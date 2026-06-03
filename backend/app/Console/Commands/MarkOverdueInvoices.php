<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    /** @var string */
    protected $signature = 'invoices:mark-overdue';

    /** @var string */
    protected $description = 'Flag pending invoices past their due date (plus grace period) as overdue and notify member + owner.';

    public function handle(InvoiceService $invoices): int
    {
        $graceDays = (int) env('INVOICE_GRACE_DAYS', 0);

        $this->info("Marking overdue invoices (grace days: {$graceDays})...");

        $flagged = $invoices->markOverdue($graceDays);

        $this->info("Flagged {$flagged} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
