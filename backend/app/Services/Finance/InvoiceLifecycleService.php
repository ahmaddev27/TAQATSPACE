<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\DocStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceLifecycleException;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceCreatedNotification;
use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invoice lifecycle for finance v2: draft (editable) -> issued (frozen, numbered)
 * -> void. Voiding replaces deletion for anything that was ever issued.
 *
 * @phpstan-type LineInput array{description?: string, qty: string|int|float, unit_price?: string|int|float, unbilled_charge_id?: string|null, catalog_item_id?: string|null}
 */
class InvoiceLifecycleService
{
    /** `invoices.amount` is DECIMAL(8,2). */
    private const MAX_TOTAL = '999999.99';

    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly UnbilledChargeService $charges,
        private readonly UnbilledChargeRepository $chargeRepository,
        private readonly AuditLogger $audit,
        private readonly InvoiceBalanceService $balances,
    ) {}

    /**
     * @param  array<string, mixed>  $data  customer_id, due_date, lines[], optional tax_rate/period_start/period_end/notes
     */
    public function createDraft(Workspace $workspace, array $data, User $actor): Invoice
    {
        $customerId = (string) $data['customer_id'];

        if (! $this->chargeRepository->isCustomerOf($workspace, $customerId)) {
            throw InvoiceLifecycleException::unknownCustomer();
        }

        $invoice = DB::transaction(function () use ($workspace, $data, $customerId): Invoice {
            $rows = $this->resolveLines($workspace, $customerId, $data['lines']);
            $totals = $this->totals($rows, (string) ($data['tax_rate'] ?? '0'));

            $invoice = Invoice::query()->create([
                'workspace_id' => $workspace->id,
                'customer_id' => $customerId,
                'subscription_id' => $this->subscriptionIdFor($workspace, $customerId),
                'doc_status' => DocStatus::Draft,
                'payment_status' => PaymentStatus::Unpaid,
                'due_date' => $data['due_date'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'notes' => $data['notes'] ?? null,
            ] + $this->moneyAttributes($totals));

            $this->insertLines($invoice, $rows);

            return $invoice;
        });

        $this->audit->log('finance.invoice.drafted', $invoice, actor: $actor, changes: ['total' => $invoice->total]);

        return $invoice->load('lines', 'customer:id,name');
    }

    /**
     * Replace a draft's lines and header fields.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvoiceLifecycleException when the invoice is no longer a draft
     */
    public function updateDraft(Invoice $invoice, array $data, User $actor): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice, $data): Invoice {
            $locked = $this->lockDraft($invoice);
            $workspace = Workspace::query()->findOrFail($locked->workspace_id);
            $rows = $this->resolveLines($workspace, (string) $locked->customer_id, $data['lines']);
            $totals = $this->totals($rows, (string) ($data['tax_rate'] ?? $locked->tax_rate));

            InvoiceLine::query()->where('invoice_id', $locked->id)->delete();
            $locked->forceFill(array_filter([
                'due_date' => $data['due_date'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], static fn (mixed $value): bool => $value !== null) + $this->moneyAttributes($totals))->save();
            $this->insertLines($locked, $rows);

            return $locked;
        });

        $this->audit->log('finance.invoice.draft_updated', $invoice, actor: $actor, changes: ['total' => $invoice->total]);

        return $invoice->load('lines', 'customer:id,name');
    }

    /**
     * Number the draft, freeze its totals and consume the unbilled charges on its lines.
     *
     * @throws InvoiceLifecycleException when the invoice is not a draft or has no lines
     */
    public function issue(Invoice $invoice, User $actor): Invoice
    {
        $issued = DB::transaction(function () use ($invoice, $actor): Invoice {
            $locked = $this->lockDraft($invoice);
            $lines = $locked->lines()->get();

            if ($lines->isEmpty()) {
                throw InvoiceLifecycleException::noLines();
            }

            $workspace = Workspace::query()->findOrFail($locked->workspace_id);
            $totals = $this->totals($lines->all(), (string) $locked->tax_rate);

            $locked->forceFill([
                'invoice_number' => $this->numbers->next($workspace),
                'doc_status' => DocStatus::Issued,
                'issued_at' => Carbon::now(),
            ] + $this->moneyAttributes($totals))->save();

            $this->charges->markInvoicedForLines($workspace, $this->lineByCharge($lines->all()), $actor);

            return $locked;
        });

        $this->audit->log('finance.invoice.issued', $issued, actor: $actor, changes: [
            'invoice_number' => $issued->invoice_number,
            'total' => $issued->total,
        ]);
        $this->afterChange($issued);
        $issued->customer?->notify(new InvoiceCreatedNotification($issued));

        return $issued->load('lines', 'customer:id,name');
    }

    /**
     * Void an issued invoice. Rows and payments are kept; charges billed on it return to the ledger.
     *
     * @throws InvoiceLifecycleException when the invoice is not issued
     */
    public function void(Invoice $invoice, string $reason, User $actor): Invoice
    {
        $voided = DB::transaction(function () use ($invoice, $reason): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->doc_status !== DocStatus::Issued) {
                throw InvoiceLifecycleException::notIssued();
            }

            $this->assertVoidable($locked);

            $locked->forceFill([
                'doc_status' => DocStatus::Void,
                'voided_at' => Carbon::now(),
                'void_reason' => $reason,
            ])->save();

            $workspace = Workspace::query()->findOrFail($locked->workspace_id);
            $this->charges->releaseForLines($workspace, $locked->lines()->pluck('id')->all());

            return $locked;
        });

        $this->audit->log('finance.invoice.voided', $voided, reason: $reason, actor: $actor, changes: [
            'invoice_number' => $voided->invoice_number,
            'total' => $voided->total,
        ]);
        $this->afterChange($voided);

        return $voided->load('lines', 'customer:id,name');
    }

    /**
     * A credit note is a numbered document against this invoice, a pending
     * cheque is money promised to it, and a wallet receipt is the customer's
     * wallet money spent on it; voiding underneath any of them would leave them
     * pointing at a dead invoice. Void or bounce them first.
     */
    private function assertVoidable(Invoice $locked): void
    {
        if (bccomp((string) $locked->credited_amount, '0', 2) > 0 || CreditNote::query()->where('invoice_id', $locked->id)->exists()) {
            throw InvoiceLifecycleException::voidHasCreditNotes();
        }

        if ($this->balances->held([$locked->id]) !== []) {
            throw InvoiceLifecycleException::voidHasPendingCheques();
        }

        if ($this->balances->hasWalletPayment($locked)) {
            throw InvoiceLifecycleException::voidHasWalletPayment();
        }
    }

    private function lockDraft(Invoice $invoice): Invoice
    {
        $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

        return $locked->isDraft() ? $locked : throw InvoiceLifecycleException::notDraft();
    }

    /**
     * Normalise input lines to rows; a line tied to an unbilled charge takes the
     * charge's own description/qty/price so the invoice can never disagree with the ledger.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function resolveLines(Workspace $workspace, string $customerId, array $lines): array
    {
        $chargeIds = array_values(array_filter(array_column($lines, 'unbilled_charge_id')));
        $charges = $this->openCharges($workspace, $customerId, $chargeIds);

        $rows = [];
        foreach (array_values($lines) as $index => $line) {
            $charge = isset($line['unbilled_charge_id']) ? $charges[$line['unbilled_charge_id']] : null;
            $qty = (string) ($charge?->qty ?? $line['qty']);
            $unitPrice = (string) ($charge?->unit_price ?? $line['unit_price']);

            $rows[] = [
                'description' => $charge?->description ?? $line['description'],
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'amount' => bcmul($qty, $unitPrice, 2),
                'unbilled_charge_id' => $charge?->id,
                'catalog_item_id' => $charge?->catalog_item_id ?? ($line['catalog_item_id'] ?? null),
                'sort_order' => $index,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<string, UnbilledCharge>
     */
    private function openCharges(Workspace $workspace, string $customerId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw InvoiceLifecycleException::chargeUnavailable();
        }

        $found = UnbilledCharge::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->where('customer_id', $customerId)
            ->where('status', 'open')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $found->count() === count($ids) ? $found->all() : throw InvoiceLifecycleException::chargeUnavailable();
    }

    /**
     * @param  array<int, array<string, mixed>|InvoiceLine>  $rows
     * @return array{subtotal: string, tax_rate: string, tax_amount: string, total: string}
     */
    private function totals(array $rows, string $taxRate): array
    {
        $subtotal = '0.00';
        foreach ($rows as $row) {
            $subtotal = bcadd($subtotal, (string) (is_array($row) ? $row['amount'] : $row->amount), 2);
        }

        // Half-up rounding of the tax to 2 decimals (bcmath alone truncates).
        $rawTax = bcdiv(bcmul($subtotal, $taxRate, 6), '100', 6);
        $taxAmount = bcadd($rawTax, '0.005', 2);
        $total = bcadd($subtotal, $taxAmount, 2);

        if (bccomp($total, self::MAX_TOTAL, 2) > 0) {
            throw InvoiceLifecycleException::totalTooLarge();
        }

        return ['subtotal' => $subtotal, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'total' => $total];
    }

    /**
     * @param  array{subtotal: string, tax_rate: string, tax_amount: string, total: string}  $totals
     * @return array<string, string>
     */
    private function moneyAttributes(array $totals): array
    {
        return $totals + ['amount' => $totals['total']];
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function insertLines(Invoice $invoice, array $rows): void
    {
        $now = Carbon::now();

        InvoiceLine::query()->insert(array_map(static fn (array $row): array => $row + [
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }

    /**
     * @param  array<int, InvoiceLine>  $lines
     * @return array<string, string>
     */
    private function lineByCharge(array $lines): array
    {
        $map = [];
        foreach ($lines as $line) {
            if ($line->unbilled_charge_id !== null) {
                $map[$line->unbilled_charge_id] = $line->id;
            }
        }

        return $map;
    }

    private function subscriptionIdFor(Workspace $workspace, string $customerId): ?string
    {
        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $customerId)
            ->orderByDesc('created_at')
            ->value('id');
    }

    private function afterChange(Invoice $invoice): void
    {
        Cache::forget("workspace:{$invoice->workspace_id}:dashboard_stats");
    }
}
