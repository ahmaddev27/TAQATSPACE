<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super-Admin Reports & Exports (Phase-4 M11)
|--------------------------------------------------------------------------
| Operational/financial tracking aggregates and memory-safe CSV exports of
| the filtered invoice / subscription / user / workspace sets. No payment
| gateway — figures are admin-tracked bookkeeping.
*/

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:view_reports'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/reports', [ReportsController::class, 'index']);
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/exports/{type}', [ExportController::class, 'download']);
    });
