<?php

declare(strict_types=1);

use App\Http\Controllers\Hotspot\RouterInstallerController;
use App\Http\Controllers\Owner\RouterInstallTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MikroTik installer (M2)
|--------------------------------------------------------------------------
| The install.rsc download is public but gated by a one-time, 30-minute token
| and rate limited. Token issuing is owner-only and behind the network flag.
*/
Route::get('v1/hotspot/install/{token}.rsc', [RouterInstallerController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:20,1');

Route::middleware(['auth:sanctum', 'role.owner', 'feature:network'])
    ->prefix('workspace/routers')
    ->group(function (): void {
        Route::post('/{router}/install-token', [RouterInstallTokenController::class, 'store']);
    });
