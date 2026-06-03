<?php

declare(strict_types=1);

use App\Http\Controllers\PackageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internet Packages — Owner CRUD + Assignment (T038, T039)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/packages')
    ->group(function (): void {
        Route::get('/', [PackageController::class, 'index']);
        Route::post('/', [PackageController::class, 'store']);
        Route::put('/{package}', [PackageController::class, 'update']);
        Route::delete('/{package}', [PackageController::class, 'destroy']);
        Route::put('/{package}/assign', [PackageController::class, 'assign']);
        Route::put('/{package}/unassign', [PackageController::class, 'unassign']);
    });
