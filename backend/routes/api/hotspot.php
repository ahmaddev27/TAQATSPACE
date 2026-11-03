<?php

declare(strict_types=1);

use App\Http\Controllers\Hotspot\HotspotSyncController;
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
    });
