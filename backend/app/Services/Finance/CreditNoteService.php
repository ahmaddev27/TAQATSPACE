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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Credit notes: the way to correct an issued invoice (BR-FIN-01). The invoice
 * itself never changes; the credit lowers what is owed, and any part of it the
 * customer had already paid is recorded as a refund owed back.
 */
class CreditNoteService
{
    private const REPEAT_WINDOW_SECONDS = 10;

    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InvoiceBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws CreditNoteException when the invoice is not issued or the amount does not fit
     */
    public function issue(Invoice $invoice, string $amount, string $reason, ?User $actor): CreditNote
    {
        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw CreditNoteException::invalidAmount();
        }

        $note = DB::transaction(fn (): CreditNote => $this->issueInTransaction($invoice, $amount, $reason, $actor));

        Cache::forget("workspace:{$note->workspace_id}:dashboard_stats");
        $this->audit->log('finance.credit_note.issued', $note, reason: $reason, actor: $actor, changes: [
            'number' => $note->number,
            'invoice_id' => $note->invoice_id,
            'amount' => $note->amount,
            // A snapshot for the trail only; the live figure is InvoiceBalanceService::refundDue.
            'refund_due_after' => $this->balances->refundDue($note->invoice),
        ]);

        return $note->load('invoice:id,invoice_number,amount,credited_amount,amount_paid,payment_status,customer_id', 'invoice.customer:id,name');
    }

    private function issueInTransaction(Invoice $invoice, string $amount, string $reason, ?User $actor): CreditNote
    {
        $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

        if ($locked->doc_status !== DocStatus::Issued) {
            throw CreditNoteException::notIssued();
        }

        $this->assertNotRepeated($locked, $amount, $reason, $actor);
        $this->assertFits($locked, $amount);
        $now = Carbon::now();

        $note = CreditNote::query()->create([
            'workspace_id' => $locked->workspace_id,
            'invoice_id' => $locked->id,
            'number' => $this->numbers->next(Workspace::query()->findOrFail($locked->workspace_id), DocumentType::CreditNote),
            'amount' => $amount,
            'reason' => $reason,
            'issued_by' => $actor?->id,
            'issued_at' => $now,
        ]);

        $this->balances->applyCredit($locked, $amount, $now);

        return $note;
    }

    /**
     * Checked inside the invoice lock, so two clicks racing each other cannot
     * both pass: the second one sees the first one's note.
     */
    private function assertNotRepeated(Invoice $invoice, string $amount, string $reason, ?User $actor): void
    {
        $repeated = CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->where('amount', $amount)
            ->where('reason', $reason)
            ->where('issued_by', $actor?->id)
            ->where('issued_at', '>=', Carbon::now()->subSeconds(self::REPEAT_WINDOW_SECONDS))
            ->exists();

        if ($repeated) {
            throw CreditNoteException::duplicate();
        }
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
