<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\UnbilledChargeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — unbilled charges
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Owners and cashiers read;
| recording and voiding need the owner or the `pos_manage_products` grant.
| DELETE voids an open charge only.
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])
    ->prefix('workspace/finance/unbilled-charges')
    ->group(function (): void {
        Route::get('/', [UnbilledChargeController::class, 'index']);
        Route::post('/', [UnbilledChargeController::class, 'store']);
        Route::delete('/{id}', [UnbilledChargeController::class, 'destroy']);
    });
