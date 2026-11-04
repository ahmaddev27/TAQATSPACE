<?php

declare(strict_types=1);

use App\Services\Finance\LegacyPaymentImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Data migration: every legacy invoice payment becomes a receipt. It is
 * idempotent and never fails the deploy: a bad row is logged and skipped, and
 * `php artisan finance:migrate-invoice-payments` can be re-run at any time.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $result = app(LegacyPaymentImporter::class)->run();
            Log::info('Legacy invoice payments copied into receipts.', $result);
        } catch (Throwable $e) {
            Log::error('Copying legacy invoice payments into receipts failed.', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // The receipts table is dropped by the schema migration's own down().
    }
};
