<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\HourPackageController;
use App\Http\Controllers\Admin\HourPurchaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super-Admin Platform Hour Packages (M5.24)
|--------------------------------------------------------------------------
| Catalog, purchase queue (activation records a payment made outside the
| platform — no gateway) and manual balance adjustments.
*/

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_billing'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/hour-packages', [HourPackageController::class, 'index']);
        Route::post('/hour-packages', [HourPackageController::class, 'store']);
        Route::put('/hour-packages/{hour_package}', [HourPackageController::class, 'update']);
        Route::delete('/hour-packages/{hour_package}', [HourPackageController::class, 'destroy']);

        Route::get('/hour-purchases', [HourPurchaseController::class, 'index']);
        Route::post('/hour-purchases/{purchase}/activate', [HourPurchaseController::class, 'activate']);
        Route::post('/hour-purchases/{purchase}/cancel', [HourPurchaseController::class, 'cancel']);
        Route::post('/hour-adjustments', [HourPurchaseController::class, 'adjust']);
    });
