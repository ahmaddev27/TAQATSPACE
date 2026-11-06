<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\ChequeStatus;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\InvoiceLifecycleService;
use App\Services\Finance\ReceiptService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

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

        return app(InvoiceLifecycleService::class)->issue($draft, $owner);
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

    /**
     * SQLite drops `FOR UPDATE`, so a lock-order test could not see it. This makes
     * the grammar keep the lock as a trailing SQL comment the query log can match.
     * MariaDB already writes `for update` into the SQL, so its grammar stays.
     */
    protected function exposeLocks(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value): string
            {
                return is_string($value) ? " /* {$value} */" : ($value ? ' /* for update */' : ' /* lock in share mode */');
            }
        });
    }

    /** @return array<int, string> the SQL of every query the action ran, in order */
    protected function queriesDuring(callable $action): array
    {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $action();

        return $queries;
    }

    /** @param  array<int, string>  $queries */
    protected function firstIndex(array $queries, string $pattern): int
    {
        foreach ($queries as $index => $sql) {
            if (preg_match($pattern, $sql) === 1) {
                return $index;
            }
        }

        $this->fail("No query matched {$pattern}");
    }

    /** @return array<int, array{invoice_id: string, amount: string}> */
    protected function allocation(Invoice $invoice, string $amount): array
    {
        return [['invoice_id' => $invoice->id, 'amount' => $amount]];
    }

    /** A cheque received for `$amount` of the invoice: it holds that part until it clears or bounces. */
    protected function pendingCheque(User $owner, Workspace $workspace, User $customer, Invoice $invoice, string $amount): Receipt
    {
        return app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, $amount, [
            'method' => 'cheque',
            'allocations' => $this->allocation($invoice, $amount),
        ]), $owner);
    }

    protected function clearedCheque(User $owner, Workspace $workspace, User $customer, Invoice $invoice, string $amount): Receipt
    {
        $service = app(ReceiptService::class);
        $cheque = $this->pendingCheque($owner, $workspace, $customer, $invoice, $amount);
        $service->changeChequeStatus($cheque, ChequeStatus::Deposited, $owner);

        return $service->changeChequeStatus($cheque->fresh(), ChequeStatus::Cleared, $owner);
    }

    /** Acts as the owner with a fresh two-factor check, as the step-up routes require. */
    protected function actAsOwner(User $owner): void
    {
        Sanctum::actingAs($owner);
        $owner->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(TwoFactorService::class)->markVerified($owner);
    }
}
