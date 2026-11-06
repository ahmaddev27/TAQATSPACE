<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\CreditNoteService;
use App\Services\Finance\PaymentReversalService;
use App\Services\Finance\PaymentVoucherService;
use App\Services\Finance\ReceiptService;
use App\Services\InvoiceService;

/**
 * Every money path locks in one global order: receipts (by id), invoices (by
 * id), the customer's wallet row, then the document number counter. Two paths
 * that took any two of these the other way round could deadlock on MariaDB
 * (1213). The wallet row, not the customer's users row, is the wallet's lock.
 */
class WalletLockOrderTest extends WalletTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->exposeLocks();
    }

    public function test_settling_locks_the_invoice_then_the_wallet_then_the_receipt_number(): void
    {
        [$owner, $workspace, $customer, $invoice] = $this->invoiceWithDeposit('100.00');

        $queries = $this->queriesWithTransactions(fn () => $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '40.00', 'settle-1', $owner));

        $this->assertLockOrder($queries, ['invoices', 'customer_wallets', 'document_sequences']);
        $this->assertNoUsersLock($queries);
    }

    public function test_settling_locks_the_invoice_before_the_first_plain_read_of_its_transaction(): void
    {
        [$owner, $workspace, $customer, $invoice] = $this->invoiceWithDeposit('100.00');

        $queries = $this->queriesWithTransactions(fn () => $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '40.00', 'settle-1', $owner));
        $inTransaction = array_slice($queries, array_search(self::BEGIN, $queries, true) + 1);

        // On MariaDB the first plain SELECT fixes the snapshot; it must come after the locks.
        $this->assertMatchesRegularExpression($this->lockOf('invoices'), $inTransaction[0]);
        $this->assertMatchesRegularExpression($this->lockOf('customer_wallets'), $inTransaction[1]);
    }

    public function test_a_credit_note_locks_the_invoice_then_the_wallet_then_the_note_number(): void
    {
        [$owner, , , $invoice] = $this->paidInvoice('100.00');

        $queries = $this->queriesWithTransactions(fn () => app(CreditNoteService::class)->issue($invoice->fresh(), '30.00', 'Discount', $owner));

        $this->assertLockOrder($queries, ['invoices', 'customer_wallets', 'document_sequences']);
        $this->assertNoUsersLock($queries);
    }

    public function test_a_receipt_void_locks_the_receipt_then_the_invoice_then_the_wallet(): void
    {
        [$owner, $workspace, $customer] = $this->paidInvoice('100.00');
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cash = app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '70.00', 'Discount', $owner);

        $queries = $this->queriesWithTransactions(fn () => app(ReceiptService::class)->void($cash->fresh(), 'Wrong customer', $owner));

        $this->assertLockOrder($queries, ['receipts', 'invoices', 'customer_wallets']);
        $this->assertNoUsersLock($queries);
    }

    public function test_voiding_a_wallet_receipt_locks_the_receipt_then_the_invoice_then_the_wallet(): void
    {
        [$owner, $workspace, $customer, $invoice] = $this->invoiceWithDeposit('100.00');
        $receipt = $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '100.00', null, $owner);

        $queries = $this->queriesWithTransactions(fn () => app(ReceiptService::class)->void($receipt->fresh(), 'Paid by mistake', $owner));

        $this->assertLockOrder($queries, ['receipts', 'invoices', 'customer_wallets']);
        $this->assertNoUsersLock($queries);
    }

    public function test_marking_unpaid_locks_the_receipts_then_the_invoice_then_the_wallet(): void
    {
        [$owner, $workspace, $customer] = $this->paidInvoice('10.00');
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        app(InvoiceService::class)->markPaid($invoice->fresh(), null, PaymentMethod::Cash);
        app(CreditNoteService::class)->issue($invoice->fresh(), '30.00', 'Discount', $owner);

        $queries = $this->queriesWithTransactions(fn () => app(PaymentReversalService::class)->reverse($invoice->fresh(), $owner));

        $this->assertLockOrder($queries, ['receipts', 'invoices', 'customer_wallets']);
        $this->assertNoUsersLock($queries);
    }

    public function test_a_voucher_void_locks_the_voucher_then_the_wallet(): void
    {
        [$owner, $workspace, $customer] = $this->invoiceWithDeposit('100.00');
        $voucher = $this->giveBack($workspace, $customer, '20.00', $owner);

        $queries = $this->queriesWithTransactions(fn () => app(PaymentVoucherService::class)->void($voucher, 'Not handed over', $owner));

        $this->assertLockOrder($queries, ['payment_vouchers', 'customer_wallets']);
        $this->assertNoUsersLock($queries);
    }

    public function test_a_deposit_return_locks_the_wallet_then_the_voucher_number_and_never_the_users_row(): void
    {
        [$owner, $workspace, $customer] = $this->invoiceWithDeposit('100.00');

        $queries = $this->queriesWithTransactions(fn () => $this->giveBack($workspace, $customer, '20.00', $owner));

        $this->assertLockOrder($queries, ['customer_wallets', 'document_sequences']);
        $this->assertNoUsersLock($queries);
    }

    /** @return array{0: User, 1: Workspace, 2: User, 3: Invoice} */
    private function invoiceWithDeposit(string $deposit): array
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, $deposit, $owner);

        return [$owner, $workspace, $customer, $invoice];
    }

    /** @return array{0: User, 1: Workspace, 2: User, 3: Invoice} */
    private function paidInvoice(string $amount): array
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, $amount);
        app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, $amount, ['allocations' => $this->allocation($invoice, $amount)]), $owner);

        return [$owner, $workspace, $customer, $invoice];
    }

    /** @param  array<int, string>  $queries */
    private function assertNoUsersLock(array $queries): void
    {
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression($this->lockOf('users'), $sql, 'the users row is not the wallet lock');
        }
    }
}
