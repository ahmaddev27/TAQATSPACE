<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\CreditNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — credit notes
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Reading needs `finance_reports`
| or `finance_issue_void`; issuing a credit note corrects a frozen invoice, so it
| needs `finance_issue_void` plus a recent two-factor check.
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])->group(function (): void {
    Route::prefix('workspace/finance/credit-notes')
        ->middleware('finance.permission:finance_reports,finance_issue_void')
        ->group(function (): void {
            Route::get('/', [CreditNoteController::class, 'index']);
            Route::get('/{id}/pdf', [CreditNoteController::class, 'pdf']);
        });

    Route::post('workspace/invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store'])
        ->middleware(['finance.permission:finance_issue_void', '2fa.enrolled', '2fa.recent']);
});
