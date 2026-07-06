<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\CashierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner — Cashier / Café staff management (POS module)
|--------------------------------------------------------------------------
| Invite, list, re-scope and deactivate the POS staff of the authenticated
| owner's workspace. All actions are scoped to that workspace; foreign
| cashiers are never listed or mutated.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/cashiers')
    ->group(function (): void {
        Route::get('/', [CashierController::class, 'index']);
        Route::post('/invite', [CashierController::class, 'invite']);
        Route::post('/invitations/{invitation}/resend', [CashierController::class, 'resendInvitation']);
        Route::delete('/invitations/{invitation}', [CashierController::class, 'destroyInvitation']);
        Route::put('/{cashier}/permissions', [CashierController::class, 'updatePermissions']);
        Route::delete('/{cashier}/permanent', [CashierController::class, 'deleteCashier']);
        Route::delete('/{cashier}', [CashierController::class, 'destroy']);
    });
