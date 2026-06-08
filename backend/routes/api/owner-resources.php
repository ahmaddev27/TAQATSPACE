<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\ResourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Workspace Resources (mini management module)
|--------------------------------------------------------------------------
| Manage the bookable/physical resources of the authenticated owner's
| workspace. All actions are scoped to the owner's workspace — foreign
| resources are never listed or mutated.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/resources')
    ->group(function (): void {
        Route::get('/', [ResourceController::class, 'index']);
        Route::post('/', [ResourceController::class, 'store']);
        Route::put('/{resource}', [ResourceController::class, 'update']);
        Route::delete('/{resource}', [ResourceController::class, 'destroy']);
    });
