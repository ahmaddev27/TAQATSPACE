<?php

declare(strict_types=1);

use App\Http\Controllers\Member\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Freelancer Dashboard (T033)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role.freelancer'])
    ->prefix('member')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'summary']);
    });
