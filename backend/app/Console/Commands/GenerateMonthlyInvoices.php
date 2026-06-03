<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    /** @var string */
    protected $signature = 'invoices:generate-monthly';

    /** @var string */
    protected $description = 'Generate one pending invoice per active subscription for the current billing month (idempotent).';

    public function handle(InvoiceService $invoices): int
    {
        $this->info('Generating monthly invoices...');

        $created = $invoices->generateMonthlyInvoices();

        $this->info("Created {$created} invoice(s).");

        return self::SUCCESS;
    }
}
