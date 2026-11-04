<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Finance\LegacyPaymentImporter;
use Illuminate\Console\Command;

class MigrateInvoicePayments extends Command
{
    protected $signature = 'finance:migrate-invoice-payments';

    protected $description = 'Copy legacy invoice payments into receipts and allocations (safe to re-run)';

    public function handle(LegacyPaymentImporter $importer): int
    {
        $result = $importer->run();

        $this->info("Copied {$result['migrated']} payment(s); {$result['failed']} could not be copied (see the log).");

        return self::SUCCESS;
    }
}
