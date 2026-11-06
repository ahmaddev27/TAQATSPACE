<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\PaymentVoucherController;
use App\Http\Controllers\Finance\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — customer wallet, deposits and payment vouchers
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Reading needs `finance_collect`,
| `finance_reports` or `finance_issue_void`; taking a deposit and paying an
| invoice from the wallet need `finance_collect`. Paying money back (a return)
| needs `finance_issue_void` and a recent two-factor check; keeping it (a
| forfeit), reversing a wrong deposit or forfeit, and voiding a voucher need
| the same. The wallet only records money handled in cash, by card or by bank;
| no payment provider is involved.
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])
    ->prefix('workspace/finance')
    ->group(function (): void {
        Route::prefix('wallet')->group(function (): void {
            Route::middleware('finance.permission:finance_collect,finance_reports,finance_issue_void')->group(function (): void {
                Route::get('/', [WalletController::class, 'index']);
                Route::get('/{customer}', [WalletController::class, 'show'])->whereUuid('customer');
            });

            Route::post('/{customer}/deposits', [WalletController::class, 'deposit'])
                ->whereUuid('customer')->middleware(['finance.permission:finance_collect', 'throttle:30,1,wallet-deposit']);
            Route::post('/{customer}/settlements', [WalletController::class, 'settle'])
                ->whereUuid('customer')->middleware(['finance.permission:finance_collect', 'throttle:30,1,wallet-settle']);
            Route::post('/{customer}/returns', [WalletController::class, 'return'])
                ->whereUuid('customer')->middleware(['finance.permission:finance_issue_void', '2fa.enrolled', '2fa.recent', 'throttle:30,1,wallet-return']);
            Route::post('/{customer}/forfeits', [WalletController::class, 'forfeit'])
                ->whereUuid('customer')->middleware(['finance.permission:finance_issue_void', '2fa.enrolled', '2fa.recent', 'throttle:30,1,wallet-forfeit']);
            Route::post('/{customer}/entries/{entry}/reverse', [WalletController::class, 'reverse'])
                ->whereUuid(['customer', 'entry'])->middleware(['finance.permission:finance_issue_void', '2fa.enrolled', '2fa.recent', 'throttle:30,1,wallet-reverse']);
        });

        Route::prefix('payment-vouchers')->group(function (): void {
            Route::middleware('finance.permission:finance_reports,finance_issue_void')->group(function (): void {
                Route::get('/', [PaymentVoucherController::class, 'index']);
                Route::get('/{id}/pdf', [PaymentVoucherController::class, 'pdf'])->whereUuid('id');
            });

            Route::post('/{id}/void', [PaymentVoucherController::class, 'void'])
                ->whereUuid('id')
                ->middleware(['finance.permission:finance_issue_void', '2fa.enrolled', '2fa.recent', 'throttle:30,1,payment-voucher-void']);
        });
    });
