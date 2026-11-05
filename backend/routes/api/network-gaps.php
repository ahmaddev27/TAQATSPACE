<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\RouterGapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Router gaps (M3)
|--------------------------------------------------------------------------
| Periods without router data, read-only, owner-only and behind the network flag.
*/
Route::middleware(['auth:sanctum', 'role.owner', 'feature:network'])
    ->prefix('workspace/network')
    ->group(function (): void {
        Route::get('/gaps', [RouterGapController::class, 'index']);
    });
