<?php

declare(strict_types=1);

use App\Http\Controllers\SeatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Seats (T026, T030)
|--------------------------------------------------------------------------
| Seat map is readable by owner or public visitors; assignee names are only
| exposed to the workspace owner/admin (enforced in the controller via the
| manage-workspace Gate). All mutations are owner-only.
*/

// Public/owner seat map for a workspace.
Route::get('/workspaces/{workspace}/seats', [SeatController::class, 'index']);

Route::middleware(['auth:sanctum', 'role.owner'])->group(function (): void {
    Route::post('/workspace/seats', [SeatController::class, 'store']);

    Route::prefix('seats/{seat}')->group(function (): void {
        Route::put('/', [SeatController::class, 'update']);
        Route::put('/assign', [SeatController::class, 'assign']);
        Route::put('/unassign', [SeatController::class, 'unassign']);
        Route::delete('/', [SeatController::class, 'destroy']);
    });
});
