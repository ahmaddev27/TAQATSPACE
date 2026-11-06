<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ChequeStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Exceptions\ReceiptException;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoicePaidNotification;
use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Services\Audit\AuditLogger;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Receipts: money taken from a customer, allocated across invoices. Only an
 * issued invoice can take money, a cheque counts once it clears, and every
 * change to a receipt moves the invoices' paid totals back in step.
 *
 * @phpstan-type AllocationInput array{invoice_id: string, amount: string|int|float}
 */
class ReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InvoiceBalanceService $balances,
        private readonly UnbilledChargeRepository $customers,
        private readonly FileUploadService $uploads,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  customer_id, method, amount, received_at?, collected_at?, reference?,
     *                                      allocations?; internally also receipt_path, receipt_disk, legacy_payment_id
     *
     * @throws ReceiptException when the customer is unknown or the allocations do not fit
     */
    public function create(Workspace $workspace, array $data, ?User $actor, ?UploadedFile $file = null): Receipt
    {
        if (! $this->customers->isCustomerOf($workspace, (string) $data['customer_id'])) {
            throw ReceiptException::unknownCustomer();
        }

        $data = $file === null ? $data : $this->storeFile($file, $workspace, $data);

        try {
            [$receipt, $paid] = DB::transaction(fn (): array => $this->createInTransaction($workspace, $data, $actor));
        } catch (Throwable $e) {
            $this->discardFile($file === null ? null : $data);
            throw $e;
        }

        $this->afterChange($receipt, $paid);
        $this->audit->log('finance.receipt.created', $receipt, actor: $actor, changes: [
            'number' => $receipt->number,
            'method' => $receipt->method->value,
            'amount' => $receipt->amount,
        ]);

        return $this->fresh($receipt);
    }

    /**
     * Allocate (more of) an existing receipt to invoices.
     *
     * @param  array<int, AllocationInput>  $allocations
     *
     * @throws ReceiptException when the receipt is void or the allocations do not fit
     */
    public function allocate(Receipt $receipt, array $allocations, ?User $actor): Receipt
    {
        [$receipt, $paid] = DB::transaction(function () use ($receipt, $allocations): array {
            $locked = $this->lock($receipt);

            return [$locked, $this->applyAllocations($locked, $allocations)];
        });

        $this->afterChange($receipt, $paid);
        $this->audit->log('finance.receipt.allocated', $receipt, actor: $actor, changes: ['allocations' => $allocations]);

        return $this->fresh($receipt);
    }

    /**
     * @throws ReceiptException when the receipt is not a cheque or the move is not allowed
     */
    public function changeChequeStatus(Receipt $receipt, ChequeStatus $target, ?User $actor, ?Carbon $collectedAt = null): Receipt
    {
        [$receipt, $from, $paid] = DB::transaction(function () use ($receipt, $target, $collectedAt): array {
            $locked = $this->lock($receipt);

            if (! $locked->isCheque()) {
                throw ReceiptException::notCheque();
            }

            $from = $locked->cheque_status;

            if (! $from->canMoveTo($target)) {
                throw ReceiptException::invalidChequeTransition();
            }

            $wasCollected = $locked->isCollected();
            $locked->forceFill([
                'cheque_status' => $target,
                'collected_at' => $target === ChequeStatus::Cleared ? ($collectedAt ?? Carbon::now()) : null,
            ])->save();

            return [$locked, $from, $this->settleCollectionChange($locked, $wasCollected)];
        });

        $this->afterChange($receipt, $paid);
        $this->audit->log('finance.receipt.cheque_changed', $receipt, actor: $actor, changes: [
            'from' => $from->value,
            'to' => $target->value,
        ]);

        return $this->fresh($receipt);
    }

    /**
     * Void a receipt: its money comes back off the invoices and the file is kept.
     *
     * @throws ReceiptException when it is already void
     */
    public function void(Receipt $receipt, string $reason, ?User $actor): Receipt
    {
        [$receipt, $paid] = DB::transaction(function () use ($receipt, $reason): array {
            $locked = $this->lock($receipt);
            $wasCollected = $locked->isCollected();

            $locked->forceFill(['voided_at' => Carbon::now(), 'void_reason' => $reason])->save();

            // The legacy payment row mirrors the receipt, so it goes with it.
            if ($locked->legacy_payment_id !== null) {
                InvoicePayment::query()->whereKey($locked->legacy_payment_id)->delete();
            }

            return [$locked, $this->settleCollectionChange($locked, $wasCollected)];
        });

        $this->afterChange($receipt, $paid);
        $this->audit->log('finance.receipt.voided', $receipt, reason: $reason, actor: $actor, changes: [
            'number' => $receipt->number,
            'amount' => $receipt->amount,
        ]);

        return $this->fresh($receipt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Receipt, 1: array<int, Invoice>}
     */
    private function createInTransaction(Workspace $workspace, array $data, ?User $actor): array
    {
        $receipt = $this->newReceipt($workspace, $data, $actor);
        $rows = $this->normalise($data['allocations'] ?? []);

        // Invoices before the receipt number. Paying an invoice and issuing a credit note
        // hold the invoice lock while they take a number; taking the number first here
        // would wait on the counter while they wait on our invoice (MariaDB 1213).
        $invoices = $rows === [] ? null : $this->balances->lockPayable($receipt, array_keys($rows));

        $receipt->number = $this->numbers->next($workspace, DocumentType::Receipt);
        $receipt->save();

        return [$receipt, $invoices === null ? [] : $this->applyToLocked($receipt, $invoices, $rows)];
    }

    /** @param  array<string, mixed>  $data */
    private function newReceipt(Workspace $workspace, array $data, ?User $actor): Receipt
    {
        $method = PaymentMethod::from((string) $data['method']);
        $receivedAt = isset($data['received_at']) ? Carbon::parse($data['received_at']) : Carbon::now();

        return new Receipt([
            'workspace_id' => $workspace->id,
            'customer_id' => $data['customer_id'],
            'method' => $method,
            'amount' => bcadd((string) $data['amount'], '0', 2),
            'received_at' => $receivedAt,
            'collected_at' => $method === PaymentMethod::Cheque ? null : (isset($data['collected_at']) ? Carbon::parse($data['collected_at']) : $receivedAt),
            'reference' => $data['reference'] ?? null,
            'cheque_status' => $method === PaymentMethod::Cheque ? ChequeStatus::Received : null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'receipt_disk' => $data['receipt_disk'] ?? null,
            'created_by' => $actor?->id,
            'legacy_payment_id' => $data['legacy_payment_id'] ?? null,
        ]);
    }

    /**
     * Validate and store more allocations on an existing receipt.
     *
     * @param  array<int, AllocationInput>  $allocations
     * @return array<int, Invoice> the invoices that became fully paid
     */
    private function applyAllocations(Receipt $receipt, array $allocations): array
    {
        if ($receipt->isVoided()) {
            throw ReceiptException::voided();
        }

        if ($allocations === []) {
            return [];
        }

        $rows = $this->normalise($allocations);

        return $this->applyToLocked($receipt, $this->balances->lockPayable($receipt, array_keys($rows)), $rows);
    }

    /**
     * Store allocations on invoices the caller has locked, and move the invoices if the money is
     * already collected. The lock must come first: on MariaDB (REPEATABLE READ) the first plain
     * SELECT in a transaction fixes its snapshot, so reading allocations before waiting for the
     * lock would miss a cheque another transaction committed meanwhile, and two cheques could
     * claim the same balance.
     *
     * @param  Collection<string, Invoice>  $invoices
     * @param  array<string, string>  $rows
     * @return array<int, Invoice> the invoices that became fully paid
     */
    private function applyToLocked(Receipt $receipt, Collection $invoices, array $rows): array
    {
        $this->assertFitsReceipt($receipt, $rows);
        $this->assertFitsInvoices($invoices, $rows);
        $this->storeAllocations($receipt, $rows);

        return $receipt->isCollected() ? $this->collect($receipt, $invoices, $rows) : [];
    }

    /**
     * @param  array<int, AllocationInput>  $allocations
     * @return array<string, string> invoice id => amount
     */
    private function normalise(array $allocations): array
    {
        $rows = [];

        foreach ($allocations as $allocation) {
            $id = (string) $allocation['invoice_id'];
            $rows[$id] = bcadd($rows[$id] ?? '0', (string) $allocation['amount'], 2);
        }

        return $rows;
    }

    /** @param  array<string, string>  $rows */
    private function assertFitsReceipt(Receipt $receipt, array $rows): void
    {
        $allocated = (string) ReceiptAllocation::query()->where('receipt_id', $receipt->id)->sum('amount');
        $requested = array_reduce($rows, static fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00');

        if (bccomp(bcadd($allocated, $requested, 2), (string) $receipt->amount, 2) > 0) {
            throw ReceiptException::exceedsReceipt();
        }
    }

    /**
     * @param  Collection<string, Invoice>  $invoices
     * @param  array<string, string>  $rows
     */
    private function assertFitsInvoices($invoices, array $rows): void
    {
        $open = $this->balances->outstanding($invoices);

        foreach ($rows as $invoiceId => $amount) {
            if (bccomp($amount, '0', 2) <= 0) {
                throw ReceiptException::invoiceMismatch();
            }

            if (bccomp($amount, $open[$invoiceId], 2) > 0) {
                throw ReceiptException::exceedsBalance(number_format((float) $open[$invoiceId], 2));
            }
        }
    }

    /**
     * A receipt holds one row per invoice, so a later allocation adds to the row already there.
     *
     * @param  array<string, string>  $rows
     */
    private function storeAllocations(Receipt $receipt, array $rows): void
    {
        $existing = ReceiptAllocation::query()
            ->where('receipt_id', $receipt->id)
            ->whereIn('invoice_id', array_keys($rows))
            ->pluck('amount', 'invoice_id');
        $now = Carbon::now();

        ReceiptAllocation::query()->upsert(array_map(static fn (string $invoiceId, string $amount): array => [
            'id' => (string) Str::uuid(),
            'receipt_id' => $receipt->id,
            'invoice_id' => $invoiceId,
            'amount' => bcadd((string) ($existing[$invoiceId] ?? '0'), $amount, 2),
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($rows), array_values($rows)), ['receipt_id', 'invoice_id'], ['amount', 'updated_at']);
    }

    /**
     * @param  Collection<string, Invoice>  $invoices
     * @param  array<string, string>  $rows
     * @return array<int, Invoice>
     */
    private function collect(Receipt $receipt, $invoices, array $rows): array
    {
        $paid = [];
        $at = $receipt->collected_at ?? Carbon::now();

        foreach ($rows as $invoiceId => $amount) {
            $invoice = $invoices[$invoiceId];
            $wasPaid = $invoice->payment_status->value === 'paid';

            $this->balances->applyDelta($invoice, $amount, $at);

            if (! $wasPaid && $invoice->payment_status->value === 'paid') {
                $paid[] = $invoice;
            }
        }

        return $paid;
    }

    /**
     * After a status change the receipt's allocations either start or stop
     * counting as collected; move the invoices by exactly that.
     *
     * @return array<int, Invoice> the invoices that became fully paid
     */
    private function settleCollectionChange(Receipt $receipt, bool $wasCollected): array
    {
        $isCollected = $receipt->isCollected();

        if ($wasCollected === $isCollected) {
            return [];
        }

        // A plain read before the invoice lock is safe here, and a locking read would take gap
        // locks that new allocations then queue behind. The caller holds this receipt's lock, and
        // adding allocations needs it, so the rows cannot change under us; and because the caller
        // started with that locking read, this is the transaction's first plain SELECT, so its
        // snapshot is taken after the lock. A caller that wraps this in a larger transaction must
        // not run a plain SELECT before locking the receipt. The invoice rows themselves come from
        // the locking read below, which always sees the latest committed values.
        $rows = ReceiptAllocation::query()->where('receipt_id', $receipt->id)->pluck('amount', 'invoice_id')
            ->map(static fn (mixed $amount): string => bcadd((string) $amount, '0', 2))->all();

        if ($rows === []) {
            return [];
        }

        $invoices = Invoice::query()->whereIn('id', array_keys($rows))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($isCollected) {
            $this->balances->assertAllIssued($invoices);

            return $this->collect($receipt, $invoices, $rows);
        }

        foreach ($rows as $invoiceId => $amount) {
            $this->balances->applyDelta($invoices[$invoiceId], bcmul($amount, '-1', 2), Carbon::now());
        }

        return [];
    }

    private function lock(Receipt $receipt): Receipt
    {
        $locked = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);

        return $locked->isVoided() ? throw ReceiptException::voided() : $locked;
    }

    private function fresh(Receipt $receipt): Receipt
    {
        return $receipt->refresh()->load('allocations.invoice:id,invoice_number,amount', 'customer:id,name');
    }

    /** @param  array<int, Invoice>  $paid */
    private function afterChange(Receipt $receipt, array $paid): void
    {
        Cache::forget("workspace:{$receipt->workspace_id}:dashboard_stats");

        foreach ($paid as $invoice) {
            $invoice->customer?->notify(new InvoicePaidNotification($invoice));
        }
    }

    /**
     * Receipts are financial documents: the proof goes on the private disk and
     * is only ever handed out through a short-lived signed URL. Pass the result
     * to create() as `receipt_path` / `receipt_disk`, and to discardProof() if
     * the payment then fails.
     *
     * @return array{receipt_path: string, receipt_disk: string}
     */
    public function storeProof(UploadedFile $file, Workspace $workspace): array
    {
        $disk = (string) config('filesystems.default');

        return [
            'receipt_path' => $this->uploads->upload($file, 'finance/receipts/'.$workspace->id, $disk, 'private'),
            'receipt_disk' => $disk,
        ];
    }

    /** @param  array<string, mixed>  $proof  what storeProof() returned; anything else is left alone */
    public function discardProof(array $proof): void
    {
        if (isset($proof['receipt_path'], $proof['receipt_disk'])) {
            Storage::disk($proof['receipt_disk'])->delete($proof['receipt_path']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeFile(UploadedFile $file, Workspace $workspace, array $data): array
    {
        return $data + $this->storeProof($file, $workspace);
    }

    /** @param  array<string, mixed>|null  $data */
    private function discardFile(?array $data): void
    {
        if ($data !== null) {
            $this->discardProof($data);
        }
    }
}
