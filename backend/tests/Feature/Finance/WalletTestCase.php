<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\WalletService;

/** Shared fixtures for the wallet and payment-voucher tests. */
abstract class WalletTestCase extends ReceiptTestCase
{
    protected const WALLET = '/api/workspace/finance/wallet';

    protected const VOUCHERS = '/api/workspace/finance/payment-vouchers';

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
}
