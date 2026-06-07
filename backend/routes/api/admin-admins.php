<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Management (super-admin only)
|--------------------------------------------------------------------------
| Create and administer staff accounts and their roles/permissions. Gated by
| `can.manage_admins` (the `manage_admins` permission, held only by the
| `super_admin` role) so a standard admin cannot manage other admins.
*/

Route::middleware(['auth:sanctum', 'can.manage_admins'])
    ->prefix('admin/admins')
    ->group(function (): void {
        Route::get('/', [AdminManagementController::class, 'index']);
        Route::post('/', [AdminManagementController::class, 'store']);
        Route::put('/{user}', [AdminManagementController::class, 'update']);
        Route::delete('/{user}', [AdminManagementController::class, 'destroy']);
    });
