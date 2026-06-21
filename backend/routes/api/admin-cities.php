<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\CityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cities — public list + admin manage
|--------------------------------------------------------------------------
| Workspaces reference a real city (FK) while keeping a denormalized display
| string. The public discovery UI reads active cities here; a Super Admin
| manages the catalogue via the admin endpoints. Mirrors the About CMS gating.
*/

// Public read: active cities only.
Route::get('/cities', [CityController::class, 'index']);

// Admin manage.
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_content'])
    ->prefix('admin/cities')
    ->group(function (): void {
        Route::get('/', [AdminCityController::class, 'index']);
        Route::post('/', [AdminCityController::class, 'store']);
        Route::put('/{city}', [AdminCityController::class, 'update']);
        Route::delete('/{city}', [AdminCityController::class, 'destroy']);
    });
