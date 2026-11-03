<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\RouterSecurityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Router security (M2)
|--------------------------------------------------------------------------
| Unlocking a router and rotating its key are owner-only and need a recent
| two-factor check, because they decide which device may speak for the workspace.
*/
Route::middleware(['auth:sanctum', 'role.owner', 'feature:network', '2fa.enrolled', '2fa.recent'])
    ->prefix('workspace/routers')
    ->group(function (): void {
        Route::post('/{router}/unlock', [RouterSecurityController::class, 'unlock']);
        Route::post('/{router}/rotate-key', [RouterSecurityController::class, 'rotateKey']);
    });
