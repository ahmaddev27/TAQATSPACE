<?php

declare(strict_types=1);

use App\Http\Controllers\Hotspot\HotspotSyncController;
use App\Http\Controllers\Hotspot\RouterInstallerController;
use App\Http\Controllers\Hotspot\RouterScriptController;
use App\Http\Controllers\Owner\RouterInstallTokenController;
use App\Http\Controllers\Owner\RouterScriptUpdateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hotspot sync (MikroTik routers, PUSH model)
|--------------------------------------------------------------------------
| Authenticated by `X-API-Key` + workspace `{code}` (`router` middleware),
| then limited per router (`throttle:router`, so it must come second).
*/

Route::middleware(['router', 'throttle:router'])
    ->prefix('v1/hotspot/{code}')
    ->group(function (): void {
        Route::post('/sync', [HotspotSyncController::class, 'store']);
        Route::get('/scripts/{name}', [RouterScriptController::class, 'show'])->where('name', '[A-Za-z0-9._-]+');
    });

// Reachability check used by install-precheck.rsc; public, so it must reveal nothing.
Route::get('v1/hotspot/ping', [RouterScriptController::class, 'ping'])->middleware('throttle:60,1');

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
        Route::get('/{router}/script-update', [RouterScriptUpdateController::class, 'show']);
        Route::post('/{router}/update-script', [RouterScriptUpdateController::class, 'store']);
    });
