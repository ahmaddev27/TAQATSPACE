<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\ToolsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner tools (M3)
|--------------------------------------------------------------------------
| Owner-only maintenance actions, behind the network flag. Recalculation is
| heavy, so starting one is rate limited on top of the one-run-at-a-time lock.
*/
Route::middleware(['auth:sanctum', 'role.owner', 'feature:network'])
    ->prefix('workspace/tools')
    ->group(function (): void {
        Route::post('/recalculate', [ToolsController::class, 'recalculate'])->middleware('throttle:6,1');
        Route::get('/recalculate/status', [ToolsController::class, 'status']);
    });
