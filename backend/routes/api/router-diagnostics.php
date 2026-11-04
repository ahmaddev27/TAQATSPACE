<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\RouterDiagnosticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Router API diagnostics (M2)
|--------------------------------------------------------------------------
| The owner reads the router's request log and runs the deep health check.
| Both are read-only, owner-only and behind the network flag.
*/
Route::middleware(['auth:sanctum', 'role.owner', 'feature:network'])
    ->prefix('workspace/routers')
    ->group(function (): void {
        Route::get('/{router}/logs', [RouterDiagnosticsController::class, 'logs']);
        Route::post('/{router}/diagnose', [RouterDiagnosticsController::class, 'diagnose']);
    });
