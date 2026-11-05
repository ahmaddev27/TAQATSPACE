<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\ReceiptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — receipts, allocation and cheque lifecycle
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Reading needs `finance_collect`
| or `finance_reports`; taking money and moving a cheque needs `finance_collect`;
| voiding needs `finance_issue_void` plus a recent two-factor check.
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])
    ->prefix('workspace/finance/receipts')
    ->group(function (): void {
        Route::get('/', [ReceiptController::class, 'index'])->middleware('finance.permission:finance_collect,finance_reports');
        Route::get('/{id}', [ReceiptController::class, 'show'])->middleware('finance.permission:finance_collect,finance_reports');

        Route::middleware('finance.permission:finance_collect')->group(function (): void {
            Route::post('/', [ReceiptController::class, 'store']);
            Route::post('/{id}/allocations', [ReceiptController::class, 'allocate']);
            Route::put('/{id}/cheque-status', [ReceiptController::class, 'chequeStatus']);
        });

        Route::post('/{id}/void', [ReceiptController::class, 'void'])
            ->middleware(['finance.permission:finance_issue_void', '2fa.enrolled', '2fa.recent']);
    });

// Receipt proofs are private; a short-lived signed URL (made by the resource) is the credential.
Route::get('finance/receipts/{receipt}/file', [ReceiptController::class, 'file'])
    ->middleware(['signed', 'throttle:60,1,receipt-proof'])
    ->name('finance.receipts.file');
