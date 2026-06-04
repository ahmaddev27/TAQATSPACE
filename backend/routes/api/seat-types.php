<?php

declare(strict_types=1);

use App\Http\Controllers\SeatTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Seat type pricing (owner-controlled per-seat-type prices)
|--------------------------------------------------------------------------
| The owner's single workspace is resolved from the authenticated user, the
| same way the workspace settings endpoint does. Authorization is enforced by
| the 'manage-workspace' Gate inside the controller.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace')
    ->group(function (): void {
        Route::put('/seat-types', [SeatTypeController::class, 'update']);
    });
