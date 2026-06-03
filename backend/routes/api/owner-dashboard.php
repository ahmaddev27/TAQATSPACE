<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Dashboard (T027)
|--------------------------------------------------------------------------
| Aggregated stats for the authenticated workspace owner.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'ownerStats']);
    });
