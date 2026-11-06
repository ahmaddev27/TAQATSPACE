<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\InvoiceBalanceService;
use App\Services\Finance\WalletLedger;
use App\Services\Finance\WalletService;
use App\Support\Finance\Decimal;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** Shared fixtures for the wallet and payment-voucher tests. */
abstract class WalletTestCase extends ReceiptTestCase
{
    protected const WALLET = '/api/workspace/finance/wallet';

    protected const VOUCHERS = '/api/workspace/finance/payment-vouchers';

    /** Marks where a transaction (or savepoint) began in queriesWithTransactions(). */
    protected const BEGIN = '-- begin transaction';

    protected function walletUrl(User $customer, string $action = ''): string
    {
        return self::WALLET.'/'.$customer->id.($action === '' ? '' : '/'.$action);
    }

    protected function service(): WalletService
    {
        return app(WalletService::class);
    }

    protected function deposit(Workspace $workspace, User $customer, string $amount, ?User $actor = null): WalletEntry
    {
        return $this->service()->receiveDeposit($workspace, $customer->id, $amount, PaymentMethod::Cash, null, null, $actor);
    }

    protected function giveBack(Workspace $workspace, User $customer, string $amount, ?User $actor = null, ?string $key = null): PaymentVoucher
    {
        return $this->service()->returnDeposit($workspace, $customer->id, $amount, PaymentMethod::Cash, 'Deposit back', null, null, $key, $actor);
    }

    protected function balanceOf(Workspace $workspace, User $customer): string
    {
        return $this->service()->balance($workspace, $customer->id);
    }

    protected function twoFactor(User $user): void
    {
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(TwoFactorService::class)->markVerified($user);
    }

    /**
     * The rule the wallet keeps for every invoice: what its entries moved to the
     * wallet equals what it owes back, equals its running total; and the stored
     * wallet balance equals the SUM of the wallet's entries.
     */
    protected function assertWalletInStep(Invoice $invoice, string $owedBack): void
    {
        $invoice = $invoice->fresh();
        $moved = Decimal::of(WalletEntry::query()->where('related_type', WalletLedger::RELATED_INVOICE)->where('related_id', $invoice->id)->sum('amount'));

        $this->assertSame($owedBack, app(InvoiceBalanceService::class)->refundDue($invoice), 'owed back');
        $this->assertSame($owedBack, $moved, 'moved by the invoice\'s entries');
        $this->assertSame($owedBack, (string) $invoice->moved_to_wallet, 'invoices.moved_to_wallet');

        foreach (CustomerWallet::query()->get() as $wallet) {
            $this->assertSame(
                $this->service()->balance(Workspace::query()->findOrFail($wallet->workspace_id), $wallet->customer_id),
                (string) $wallet->balance,
                'customer_wallets.balance equals the SUM of the entries',
            );
        }
    }

    /** @return array<int, string> SQL of every query `$action` ran, with BEGIN markers where transactions began */
    protected function queriesWithTransactions(callable $action): array
    {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        Event::listen(TransactionBeginning::class, static function () use (&$queries): void {
            $queries[] = self::BEGIN;
        });
        $action();

        return $queries;
    }

    /** The pattern of a locking read of `$table` (MariaDB `for update`, SQLite via exposeLocks). */
    protected function lockOf(string $table): string
    {
        return '/^select .* from [`"]'.$table.'[`"] .*for update/is';
    }

    /**
     * @param  array<int, string>  $queries
     * @param  array<int, string>  $tables  in the order their first locking reads must appear
     */
    protected function assertLockOrder(array $queries, array $tables): void
    {
        $positions = array_map(fn (string $table): int => $this->firstIndex($queries, $this->lockOf($table)), $tables);
        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'locks must be taken in the order '.implode(' -> ', $tables));
    }
}
