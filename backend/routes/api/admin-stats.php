<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super-Admin Dashboard Stats (Phase-4)
|--------------------------------------------------------------------------
| Platform-wide counters for the admin dashboard.
*/

Route::middleware(['auth:sanctum', 'role.admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/stats', [DashboardController::class, 'stats']);
    });
