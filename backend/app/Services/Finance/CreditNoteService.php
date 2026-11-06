<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\DocStatus;
use App\Enums\DocumentType;
use App\Exceptions\CreditNoteException;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Credit notes: the way to correct an issued invoice (BR-FIN-01). The invoice
 * itself never changes; the credit lowers what is owed, and any part of it the
 * customer had already paid is owed back (InvoiceBalanceService::refundDue).
 */
class CreditNoteService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InvoiceBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * A repeat of a request with the same `$requestKey` returns the note the
     * first one issued (check `wasRecentlyCreated`) instead of a second credit.
     *
     * @throws CreditNoteException when the invoice is not issued, the amount does not fit, or the key issued a different note
     */
    public function issue(Invoice $invoice, string $amount, string $reason, ?User $actor, ?string $requestKey = null): CreditNote
    {
        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw CreditNoteException::invalidAmount();
        }

        try {
            $note = DB::transaction(fn (): CreditNote => $this->issueInTransaction($invoice, $amount, $reason, $actor, $requestKey));
        } catch (UniqueConstraintViolationException $e) {
            // Two requests with one key on two invoices both passed the lookup; the index kept the first.
            throw $requestKey === null ? $e : CreditNoteException::requestKeyReused();
        }

        if ($note->wasRecentlyCreated) {
            $this->afterIssue($note, $reason, $actor);
        }

        return $note->load('invoice:id,invoice_number,amount,credited_amount,amount_paid,payment_status,customer_id', 'invoice.customer:id,name');
    }

    private function issueInTransaction(Invoice $invoice, string $amount, string $reason, ?User $actor, ?string $requestKey): CreditNote
    {
        // The lock comes first: the key lookup below is then the transaction's first plain read, so
        // on MariaDB it sees a note that a racing repeat committed while this one waited.
        $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $repeat = $requestKey === null ? null : $this->noteForKey($locked, $requestKey);

        if ($repeat !== null) {
            return $this->assertSameRequest($repeat, $locked, $amount, $reason);
        }

        if ($locked->doc_status !== DocStatus::Issued) {
            throw CreditNoteException::notIssued();
        }

        $this->assertFits($locked, $amount);
        $now = Carbon::now();

        $note = CreditNote::query()->create([
            'workspace_id' => $locked->workspace_id,
            'invoice_id' => $locked->id,
            'number' => $this->numbers->next(Workspace::query()->findOrFail($locked->workspace_id), DocumentType::CreditNote),
            'amount' => $amount,
            'reason' => $reason,
            'request_key' => $requestKey,
            'issued_by' => $actor?->id,
            'issued_at' => $now,
        ]);

        $this->balances->applyCredit($locked, $amount, $now);

        return $note;
    }

    private function noteForKey(Invoice $invoice, string $requestKey): ?CreditNote
    {
        return CreditNote::query()->where('workspace_id', $invoice->workspace_id)->where('request_key', $requestKey)->first();
    }

    /** A key stands for one request: the same invoice, amount and reason, or it was reused by mistake. */
    private function assertSameRequest(CreditNote $note, Invoice $invoice, string $amount, string $reason): CreditNote
    {
        $same = $note->invoice_id === $invoice->id
            && bccomp((string) $note->amount, $amount, 2) === 0
            && $note->reason === $reason;

        return $same ? $note : throw CreditNoteException::requestKeyReused();
    }

    private function afterIssue(CreditNote $note, string $reason, ?User $actor): void
    {
        Cache::forget("workspace:{$note->workspace_id}:dashboard_stats");
        $this->audit->log('finance.credit_note.issued', $note, reason: $reason, actor: $actor, changes: [
            'number' => $note->number,
            'invoice_id' => $note->invoice_id,
            'amount' => $note->amount,
            // A snapshot for the trail only; the live figure is InvoiceBalanceService::refundDue.
            'refund_due_after' => $this->balances->refundDue($note->invoice),
        ]);
    }

    /**
     * A credit can never exceed what the invoice still carries, and while a
     * cheque is pending it may only lower the part nobody has paid or promised,
     * so a later clearing cheque cannot overpay the invoice.
     */
    private function assertFits(Invoice $invoice, string $amount): void
    {
        $creditable = bcsub((string) $invoice->amount, (string) $invoice->credited_amount, 2);

        if (bccomp($amount, $creditable, 2) > 0) {
            throw CreditNoteException::exceedsInvoice(number_format((float) $creditable, 2, '.', ''));
        }

        $open = $this->balances->outstanding(collect([$invoice->id => $invoice]))[$invoice->id];
        $holds = $this->balances->held([$invoice->id]) !== [];

        if ($holds && bccomp($amount, $open, 2) > 0) {
            throw CreditNoteException::heldByCheques(number_format((float) $open, 2, '.', ''));
        }
    }
}
