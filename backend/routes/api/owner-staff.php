<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner — Workspace staff management (cashier / hospitality / finance groups)
|--------------------------------------------------------------------------
| Invite, list, re-scope and deactivate the staff of the authenticated
| owner's workspace. All actions are scoped to that workspace; foreign staff
| are never listed or mutated.
|
| `workspace/cashiers` is the legacy prefix, kept as an alias of
| `workspace/staff` so existing clients keep working.
*/
foreach (['workspace/staff', 'workspace/cashiers'] as $prefix) {
    Route::middleware(['auth:sanctum', 'role.owner'])
        ->prefix($prefix)
        ->group(function (): void {
            Route::get('/', [StaffController::class, 'index']);
            Route::post('/invite', [StaffController::class, 'invite']);
            Route::post('/invitations/{invitation}/resend', [StaffController::class, 'resendInvitation']);
            Route::delete('/invitations/{invitation}', [StaffController::class, 'destroyInvitation']);
            Route::put('/{cashier}/permissions', [StaffController::class, 'updatePermissions']);
            Route::delete('/{cashier}/permanent', [StaffController::class, 'deleteStaff']);
            Route::delete('/{cashier}', [StaffController::class, 'destroy']);
        });
}
