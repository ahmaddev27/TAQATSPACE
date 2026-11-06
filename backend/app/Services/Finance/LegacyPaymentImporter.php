<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies each legacy `invoice_payments` row into one receipt with one
 * allocation. Safe to run any number of times: a row already copied (tracked by
 * `receipts.legacy_payment_id`) is skipped, and a row that cannot be copied is
 * logged and left for the next run instead of stopping the rest. Invoice totals
 * are never touched: the legacy payments are already counted in `amount_paid`.
 */
class LegacyPaymentImporter
{
    private const CHUNK = 200;

    /** @var array<string, Workspace|null> */
    private array $workspaces = [];

    public function __construct(private readonly DocumentNumberService $numbers) {}

    /** @return array{migrated: int, failed: int} */
    public function run(): array
    {
        $result = ['migrated' => 0, 'failed' => 0];

        InvoicePayment::query()
            ->whereNotIn('id', Receipt::query()->whereNotNull('legacy_payment_id')->select('legacy_payment_id'))
            ->chunkById(self::CHUNK, function (Collection $payments) use (&$result): void {
                $invoices = $this->invoicesFor($payments);

                foreach ($payments as $payment) {
                    $this->importOne($payment, $invoices[$payment->invoice_id] ?? null) ? $result['migrated']++ : $result['failed']++;
                }
            });

        return $result;
    }

    private function importOne(InvoicePayment $payment, ?Invoice $invoice): bool
    {
        try {
            $workspace = $invoice === null ? null : $this->workspaceOf($invoice);
            $customerId = $invoice?->customer_id ?? $invoice?->subscription?->member_id;

            if ($invoice === null || $workspace === null) {
                throw new \DomainException('The payment has no invoice or workspace.');
            }

            DB::transaction(fn () => $this->copy($payment, $invoice, $workspace, $customerId));

            return true;
        } catch (Throwable $e) {
            Log::warning('Legacy invoice payment could not be copied into a receipt.', [
                'invoice_payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function copy(InvoicePayment $payment, Invoice $invoice, Workspace $workspace, ?string $customerId): void
    {
        // Invoice before the receipt number, as every payment path locks: the allocation insert
        // below takes a shared lock on the invoice, and a payment holding the invoice while it
        // waits for the number would otherwise deadlock with this copy.
        Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first(['id']);

        $receipt = Receipt::query()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customerId,
            'number' => $this->numbers->next($workspace, DocumentType::Receipt),
            'method' => PaymentMethod::Cash,
            'amount' => $payment->amount,
            'received_at' => $payment->paid_at,
            'collected_at' => $payment->paid_at,
            'receipt_path' => $payment->receipt_path,
            // Legacy proofs were written to the media disk.
            'receipt_disk' => $payment->receipt_path === null ? null : (string) config('filesystems.media', 'public'),
            'legacy_payment_id' => $payment->id,
        ]);

        $now = now();
        DB::table('receipt_allocations')->insert([
            'id' => (string) Str::uuid(),
            'receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => $payment->amount,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  Collection<int, InvoicePayment>  $payments
     * @return Collection<string, Invoice>
     */
    private function invoicesFor(Collection $payments): Collection
    {
        return Invoice::query()->with('subscription:id,workspace_id,member_id')
            ->whereIn('id', $payments->pluck('invoice_id')->unique()->all())
            ->get()->keyBy('id');
    }

    private function workspaceOf(Invoice $invoice): ?Workspace
    {
        $id = $invoice->workspace_id ?? $invoice->subscription?->workspace_id;

        if ($id === null) {
            return null;
        }

        return $this->workspaces[$id] ??= Workspace::query()->find($id);
    }
}
