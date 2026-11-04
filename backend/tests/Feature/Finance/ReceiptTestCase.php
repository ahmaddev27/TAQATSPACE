<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Notification;

/** Shared fixtures for the receipt tests. */
abstract class ReceiptTestCase extends InvoiceV2TestCase
{
    protected const RECEIPTS = '/api/workspace/finance/receipts';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** An issued invoice of exactly `$amount` for the customer. */
    protected function invoiceOf(User $owner, Workspace $workspace, User $customer, string $amount): Invoice
    {
        $draft = $this->draftFor($owner, $workspace, $customer, [
            'lines' => [['description' => 'Service', 'qty' => '1', 'unit_price' => $amount]],
        ]);

        return app(\App\Services\Finance\InvoiceLifecycleService::class)->issue($draft, $owner);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    protected function receiptPayload(User $customer, string $amount, array $override = []): array
    {
        return [...[
            'customer_id' => $customer->id,
            'method' => 'cash',
            'amount' => $amount,
        ], ...$override];
    }

    /** @return array<int, array{invoice_id: string, amount: string}> */
    protected function allocation(Invoice $invoice, string $amount): array
    {
        return [['invoice_id' => $invoice->id, 'amount' => $amount]];
    }
}
