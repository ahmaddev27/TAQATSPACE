<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\InvoiceReceiptRejectedNotification;
use App\Notifications\InvoiceReceiptSubmittedNotification;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InvoiceService
{
    private const REMINDER_TTL_HOURS = 24;

    public function __construct(
        private readonly FileUploadService $uploads,
    ) {}

    /**
     * A freelancer submits proof of payment: store the receipt and move the
     * invoice to "under review" so the owner can verify it before confirming.
     * Allowed only while the invoice is still unpaid (pending/overdue).
     */
    public function submitReceipt(Invoice $invoice, UploadedFile $receipt): Invoice
    {
        $invoice->forceFill([
            'receipt_path' => $this->uploads->upload(
                $receipt,
                'receipts/'.$invoice->id,
                (string) config('filesystems.media', 'public'),
                'public',
            ),
            'status' => InvoiceStatus::UnderReview->value,
            // Clear any prior rejection so a re-submission starts a fresh review.
            'receipt_rejected_reason' => null,
            'receipt_reviewed_at' => null,
        ])->save();

        $invoice->loadMissing('subscription.member', 'subscription.workspace.owner');

        $invoice->subscription?->workspace?->owner?->notify(
            new InvoiceReceiptSubmittedNotification($invoice),
        );

        return $invoice;
    }

    /**
     * Owner approves a member-submitted receipt: the invoice is confirmed paid
     * (keeping the member's receipt as proof) and the member is notified.
     *
     * @throws RuntimeException when the invoice is not awaiting review
     */
    public function approveReceipt(Invoice $invoice, ?Carbon $paidAt = null): Invoice
    {
        if ($invoice->status !== InvoiceStatus::UnderReview) {
            throw new RuntimeException(__('messages.invoice_receipt_not_under_review'));
        }

        $invoice->forceFill(['receipt_reviewed_at' => Carbon::now()])->save();

        return $this->markPaid($invoice, $paidAt);
    }

    /**
     * Owner rejects a member-submitted receipt: the invoice moves to
     * "payment rejected" with the reason, and the member is notified so they can
     * fix it and upload a new receipt.
     *
     * @throws RuntimeException when the invoice is not awaiting review
     */
    public function rejectReceipt(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status !== InvoiceStatus::UnderReview) {
            throw new RuntimeException(__('messages.invoice_receipt_not_under_review'));
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::PaymentRejected->value,
            'receipt_rejected_reason' => $reason,
            'receipt_reviewed_at' => Carbon::now(),
        ])->save();

        $invoice->loadMissing('subscription.member', 'subscription.workspace');

        $invoice->subscription?->member?->notify(
            new InvoiceReceiptRejectedNotification($invoice),
        );

        return $invoice;
    }

    /**
     * Owner-side: attach a payment receipt AND record the invoice as paid in one
     * step — the owner is logging a payment they received (cash/transfer) with
     * proof. Stores the receipt, marks paid, and notifies the member.
     *
     * @throws RuntimeException when the invoice is already paid
     */
    public function recordPaymentWithReceipt(Invoice $invoice, UploadedFile $receipt, ?Carbon $paidAt = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new RuntimeException(__('messages.invoice_already_paid'));
        }

        $invoice->forceFill([
            'receipt_path' => $this->uploads->upload(
                $receipt,
                'receipts/'.$invoice->id,
                (string) config('filesystems.media', 'public'),
                'public',
            ),
            'status' => InvoiceStatus::Paid->value,
            'amount_paid' => $invoice->amount,
            'paid_at' => $paidAt ?? Carbon::now(),
        ])->save();

        $invoice->loadMissing('subscription.member', 'subscription.workspace');

        $invoice->subscription?->member?->notify(new InvoicePaidNotification($invoice));

        $workspaceId = $invoice->subscription?->workspace_id;

        if ($workspaceId !== null) {
            Cache::forget("workspace:{$workspaceId}:dashboard_stats");
        }

        return $invoice;
    }

    /**
     * Permanently delete an invoice (owner action). Removes any stored receipt
     * files first — the invoice's own and each ledger payment's — then deletes
     * the invoice; payment rows cascade via the foreign key.
     */
    public function delete(Invoice $invoice): void
    {
        $disk = Storage::disk((string) config('filesystems.media', 'public'));

        $paths = $invoice->payments()
            ->pluck('receipt_path')
            ->push($invoice->receipt_path)
            ->filter()
            ->unique();

        foreach ($paths as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $workspaceId = $invoice->subscription?->workspace_id;

        $invoice->delete();

        if ($workspaceId !== null) {
            Cache::forget("workspace:{$workspaceId}:dashboard_stats");
        }
    }

    /**
     * Idempotently generate one invoice per active subscription for the current
     * billing month. Returns the number of invoices created.
     */
    public function generateMonthlyInvoices(?Carbon $month = null): int
    {
        $month ??= Carbon::today();
        $dueDate = $month->copy()->startOfMonth()->day(15);

        $created = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function (Builder $query) use ($month): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $month->toDateString());
            })
            ->with('member', 'workspace')
            ->chunkById(100, function ($subscriptions) use ($month, $dueDate, &$created): void {
                foreach ($subscriptions as $subscription) {
                    $invoice = $this->createForBillingMonth($subscription, $month, $dueDate);

                    if ($invoice === null) {
                        continue;
                    }

                    $created++;

                    $subscription->member?->notify(new InvoiceCreatedNotification($invoice));
                }
            });

        return $created;
    }

    /**
     * Create a pending invoice for the subscription's current billing month,
     * unless one already exists (idempotency guard).
     */
    private function createForBillingMonth(Subscription $subscription, Carbon $month, Carbon $dueDate): ?Invoice
    {
        $exists = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereYear('due_date', $month->year)
            ->whereMonth('due_date', $month->month)
            ->exists();

        if ($exists) {
            return null;
        }

        return Invoice::create([
            'subscription_id' => $subscription->id,
            'amount' => $subscription->monthly_price,
            'due_date' => $dueDate->toDateString(),
            'status' => InvoiceStatus::Pending->value,
            'invoice_number' => $this->nextInvoiceNumber($subscription->workspace),
        ]);
    }

    /**
     * Raise the next pending invoice for a renewed subscription period, due on
     * the new period-end date. Idempotent: returns the existing invoice when one
     * already covers that due date (guards against a double renew click). The
     * member is notified of the freshly created invoice, matching monthly billing.
     */
    public function createForRenewal(Subscription $subscription, Carbon $periodEnd): Invoice
    {
        $existing = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('due_date', $periodEnd->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'amount' => $subscription->monthly_price,
            'due_date' => $periodEnd->toDateString(),
            'status' => InvoiceStatus::Pending->value,
            'invoice_number' => $this->nextInvoiceNumber($subscription->workspace),
        ]);

        $subscription->loadMissing('member');
        $subscription->member?->notify(new InvoiceCreatedNotification($invoice));

        return $invoice;
    }

    /**
     * Manual invoice raised by an owner against a member's active subscription
     * inside the given workspace.
     *
     * @param  array{member_id: string, amount: float|string, due_date: string, notes?: string|null}  $data
     *
     * @throws RuntimeException when the member has no active subscription here
     */
    public function createManual(Workspace $workspace, array $data): Invoice
    {
        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $data['member_id'])
            ->where('status', SubscriptionStatus::Active->value)
            ->first();

        if ($subscription === null) {
            throw new RuntimeException(__('messages.invoice_member_no_subscription'));
        }

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'amount' => $data['amount'],
            'due_date' => $data['due_date'],
            'status' => InvoiceStatus::Pending->value,
            'invoice_number' => $this->nextInvoiceNumber($workspace),
            'notes' => $data['notes'] ?? null,
        ]);

        $subscription->loadMissing('member');
        $subscription->member?->notify(new InvoiceCreatedNotification($invoice));

        return $invoice;
    }

    /**
     * Record a payment against an invoice and notify the member.
     *
     * @throws RuntimeException when the invoice is already paid
     */
    public function markPaid(Invoice $invoice, ?Carbon $paidAt = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new RuntimeException(__('messages.invoice_already_paid'));
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Paid->value,
            'amount_paid' => $invoice->amount,
            'paid_at' => $paidAt ?? Carbon::now(),
        ])->save();

        $invoice->loadMissing('subscription.member', 'subscription.workspace');

        $invoice->subscription?->member?->notify(new InvoicePaidNotification($invoice));

        $workspaceId = $invoice->subscription?->workspace_id;

        if ($workspaceId !== null) {
            Cache::forget("workspace:{$workspaceId}:dashboard_stats");
        }

        return $invoice;
    }

    /**
     * Record a partial (or final) manual payment against an invoice: add to the
     * running total and move the invoice to "partially paid", or "paid" once the
     * full amount is reached. Notifies the member only on full settlement.
     *
     * @throws RuntimeException when the invoice is already paid or the amount is invalid
     */
    public function recordPartialPayment(Invoice $invoice, float $amount, ?Carbon $paidAt = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new RuntimeException(__('messages.invoice_already_paid'));
        }

        if ($amount <= 0) {
            throw new RuntimeException(__('messages.invoice_partial_amount_invalid'));
        }

        $total = (float) $invoice->amount;
        $remaining = round($total - (float) $invoice->amount_paid, 2);

        if ($amount > $remaining + 0.001) {
            throw new RuntimeException(__('messages.invoice_payment_exceeds_remaining', [
                'remaining' => number_format($remaining, 2),
            ]));
        }

        $newPaid = (float) $invoice->amount_paid + $amount;
        $fullyPaid = $newPaid >= $total;
        $paymentDate = $paidAt ?? Carbon::now();

        DB::transaction(function () use ($invoice, $amount, $paymentDate, $fullyPaid, $newPaid): void {
            $invoice->forceFill([
                'amount_paid' => $fullyPaid ? $invoice->amount : round($newPaid, 2),
                'status' => $fullyPaid
                    ? InvoiceStatus::Paid->value
                    : InvoiceStatus::PartiallyPaid->value,
                'paid_at' => $fullyPaid ? $paymentDate : null,
            ])->save();

            $invoice->payments()->create([
                'amount' => round($amount, 2),
                'receipt_path' => null,
                'paid_at' => $paymentDate,
            ]);
        });

        $invoice->loadMissing('subscription.member', 'subscription.workspace');

        if ($fullyPaid) {
            $invoice->subscription?->member?->notify(new InvoicePaidNotification($invoice));
        }

        $workspaceId = $invoice->subscription?->workspace_id;

        if ($workspaceId !== null) {
            Cache::forget("workspace:{$workspaceId}:dashboard_stats");
        }

        return $invoice;
    }

    /**
     * Unified owner payment: record a payment (partial or full) WITH a receipt as
     * proof and an optional date. Adds to the running total, stores the receipt,
     * and moves the invoice to "partially paid" or "paid" — notifying the member
     * once it is fully settled. The single owner-facing payment action.
     *
     * @throws RuntimeException when the invoice is already paid or the amount is invalid
     */
    public function recordPayment(Invoice $invoice, float $amount, UploadedFile $receipt, ?Carbon $paidAt = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new RuntimeException(__('messages.invoice_already_paid'));
        }

        if ($amount <= 0) {
            throw new RuntimeException(__('messages.invoice_partial_amount_invalid'));
        }

        $total = (float) $invoice->amount;
        $remaining = round($total - (float) $invoice->amount_paid, 2);

        if ($amount > $remaining + 0.001) {
            throw new RuntimeException(__('messages.invoice_payment_exceeds_remaining', [
                'remaining' => number_format($remaining, 2),
            ]));
        }

        $newPaid = (float) $invoice->amount_paid + $amount;
        $fullyPaid = $newPaid >= $total;
        $paymentDate = $paidAt ?? Carbon::now();

        $receiptPath = $this->uploads->upload(
            $receipt,
            'receipts/'.$invoice->id,
            (string) config('filesystems.media', 'public'),
            'public',
        );

        DB::transaction(function () use ($invoice, $amount, $receiptPath, $paymentDate, $fullyPaid, $newPaid): void {
            $invoice->forceFill([
                'receipt_path' => $receiptPath,
                'amount_paid' => $fullyPaid ? $invoice->amount : round($newPaid, 2),
                'status' => $fullyPaid
                    ? InvoiceStatus::Paid->value
                    : InvoiceStatus::PartiallyPaid->value,
                'paid_at' => $fullyPaid ? $paymentDate : null,
                // An owner-recorded payment supersedes any prior receipt rejection.
                'receipt_rejected_reason' => null,
            ])->save();

            $invoice->payments()->create([
                'amount' => round($amount, 2),
                'receipt_path' => $receiptPath,
                'paid_at' => $paymentDate,
            ]);
        });

        $invoice->loadMissing('subscription.member', 'subscription.workspace');

        if ($fullyPaid) {
            $invoice->subscription?->member?->notify(new InvoicePaidNotification($invoice));
        }

        $workspaceId = $invoice->subscription?->workspace_id;

        if ($workspaceId !== null) {
            Cache::forget("workspace:{$workspaceId}:dashboard_stats");
        }

        return $invoice;
    }

    /**
     * Dispatch a payment reminder, rate-limited to once per 24h per invoice.
     *
     * @throws RuntimeException when a reminder was already sent in the window
     */
    public function sendReminder(Invoice $invoice): void
    {
        $cacheKey = "invoice:{$invoice->id}:reminder_sent";

        if (Cache::has($cacheKey)) {
            throw new RuntimeException(__('messages.invoice_reminder_recently_sent'));
        }

        $invoice->loadMissing('subscription.member');
        $invoice->subscription?->member?->notify(new InvoiceReminderNotification($invoice));

        Cache::put($cacheKey, true, now()->addHours(self::REMINDER_TTL_HOURS));
    }

    /**
     * Flag pending invoices whose due date (plus grace days) has passed and
     * notify both the member and the workspace owner. Returns the count flagged.
     */
    public function markOverdue(int $graceDays = 0): int
    {
        $cutoff = Carbon::today()->subDays($graceDays);
        $flagged = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Pending->value)
            ->whereDate('due_date', '<', $cutoff->toDateString())
            ->with(['subscription.member', 'subscription.workspace.owner'])
            ->chunkById(100, function ($invoices) use (&$flagged): void {
                foreach ($invoices as $invoice) {
                    $invoice->forceFill(['status' => InvoiceStatus::Overdue->value])->save();
                    $flagged++;

                    $member = $invoice->subscription?->member;
                    $owner = $invoice->subscription?->workspace?->owner;

                    $member?->notify(new InvoiceOverdueNotification($invoice, 'member'));
                    $owner?->notify(new InvoiceOverdueNotification($invoice, 'owner'));
                }
            });

        return $flagged;
    }

    /**
     * Re-remind members about invoices that are STILL unpaid past their due date
     * (overdue, or partially paid). `markOverdue` only notifies once at the
     * transition; this chases the lingering balance on a recurring basis,
     * throttled to one reminder per invoice per `$cooldownDays`.
     */
    public function remindOverdue(int $cooldownDays = 7): int
    {
        $sent = 0;

        Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Overdue->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->with('subscription.member')
            ->chunkById(100, function ($invoices) use (&$sent, $cooldownDays): void {
                foreach ($invoices as $invoice) {
                    $member = $invoice->subscription?->member;

                    if ($member === null) {
                        continue;
                    }

                    $key = "invoice:{$invoice->id}:overdue_reminder";

                    if (Cache::has($key)) {
                        continue;
                    }

                    $member->notify(new InvoiceOverdueNotification($invoice, 'member'));
                    Cache::put($key, true, Carbon::now()->addDays($cooldownDays));
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Paginated invoices for an owner's workspace, filterable by status/month.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForWorkspace(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->forWorkspace($workspace->id)
            ->with(['subscription.member', 'subscription.seat', 'payments'])
            ->orderByDesc('due_date')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Filtered invoices for an owner's workspace (no pagination), newest due
     * first, with the member chain loaded — for memory-safe cursor CSV export.
     * Reuses the same filter pipeline as the owner list so the export honours
     * exactly the filters the owner sees.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public function exportQueryForWorkspace(Workspace $workspace, array $filters): Builder
    {
        return $this->filteredQuery($filters)
            ->forWorkspace($workspace->id)
            ->with(['subscription.member:id,name,email,phone', 'subscription.seat:id,seat_number'])
            ->orderByDesc('due_date');
    }

    /**
     * Paginated invoices owned by a single member.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForMember(User $member, array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->forMember($member->id)
            ->with(['subscription.workspace', 'subscription.seat', 'payments'])
            ->orderByDesc('due_date')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Summary tiles for the owner invoices dashboard.
     *
     * @return array{total_outstanding: string, total_overdue: string, paid_this_month: string}
     */
    public function workspaceSummary(Workspace $workspace): array
    {
        $base = fn (): Builder => Invoice::query()->forWorkspace($workspace->id);

        $outstanding = (clone $base())
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])
            ->sum('amount');

        $overdue = (clone $base())
            ->where('status', InvoiceStatus::Overdue->value)
            ->sum('amount');

        $paidThisMonth = (clone $base())
            ->where('status', InvoiceStatus::Paid->value)
            ->whereYear('paid_at', Carbon::today()->year)
            ->whereMonth('paid_at', Carbon::today()->month)
            ->sum('amount');

        return [
            'total_outstanding' => number_format((float) $outstanding, 2, '.', ''),
            'total_overdue' => number_format((float) $overdue, 2, '.', ''),
            'paid_this_month' => number_format((float) $paidThisMonth, 2, '.', ''),
        ];
    }

    /**
     * Load an invoice for the owner detail view (subscription + member + seat).
     */
    public function findForWorkspace(Workspace $workspace, string $invoiceId): ?Invoice
    {
        return Invoice::query()
            ->forWorkspace($workspace->id)
            ->with(['subscription.member', 'subscription.seat', 'subscription.workspace'])
            ->find($invoiceId);
    }

    /**
     * Whether the given user may access this invoice (owner of the workspace or
     * the member it belongs to).
     */
    public function userCanAccess(Invoice $invoice, User $user): bool
    {
        $invoice->loadMissing('subscription.workspace');

        $subscription = $invoice->subscription;

        if ($subscription === null) {
            return false;
        }

        if ($subscription->member_id === $user->id) {
            return true;
        }

        return $subscription->workspace?->owner_id === $user->id;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Invoice::query();

        if (! empty($filters['status']) && InvoiceStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['month'])) {
            [$year, $month] = $this->parseMonth((string) $filters['month']);

            if ($year !== null && $month !== null) {
                $query->whereYear('due_date', $year)->whereMonth('due_date', $month);
            }
        }

        return $query;
    }

    /**
     * Parse a "YYYY-MM" filter into [year, month].
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function parseMonth(string $value): array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $matches) !== 1) {
            return [null, null];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 100));
    }

    /**
     * Generate the next sequential invoice number, prefixed by the workspace so
     * the document reads in the space's own name (e.g. TEST-{YYYY}-{0001}) rather
     * than the platform's. Locks the latest row for that prefix to keep the
     * sequence collision-safe under concurrent generation.
     */
    private function nextInvoiceNumber(Workspace $workspace): string
    {
        $year = Carbon::today()->year;
        $prefix = $this->invoicePrefixFor($workspace)."-{$year}-";

        return DB::transaction(function () use ($prefix): string {
            $latest = Invoice::query()
                ->where('invoice_number', 'like', $prefix.'%')
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->value('invoice_number');

            $sequence = 1;

            if (is_string($latest)) {
                $sequence = ((int) substr($latest, strlen($prefix))) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * A short, stable, file-safe prefix derived from the workspace name. Keeps
     * ASCII letters/digits (upper-cased, capped); falls back to a code from the
     * workspace id for names that are entirely non-Latin (e.g. Arabic only).
     */
    private function invoicePrefixFor(Workspace $workspace): string
    {
        $base = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $workspace->name));
        $base = substr($base, 0, 6);

        if ($base === '') {
            $idPart = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $workspace->id));
            $base = 'WS'.substr($idPart, 0, 4);
        }

        return $base;
    }
}
