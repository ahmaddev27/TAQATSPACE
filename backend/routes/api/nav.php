<?php

declare(strict_types=1);

use App\Http\Controllers\NavActionCountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sidebar action-count badges
|--------------------------------------------------------------------------
| Role-agnostic endpoint returning the "needs your action" counters for the
| authenticated caller (approve/reject queues), used to render sidebar badges.
*/

Route::middleware('auth:sanctum')
    ->prefix('nav')
    ->group(function (): void {
        Route::get('/action-counts', [NavActionCountController::class, 'index']);
    });
