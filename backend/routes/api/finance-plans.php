<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\PlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — plans (six pricing methods)
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Owners and cashiers read;
| changes need the owner. DELETE removes an unused plan and archives a used one.
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])
    ->prefix('workspace/finance/plans')
    ->group(function (): void {
        Route::get('/', [PlanController::class, 'index']);
        Route::post('/', [PlanController::class, 'store']);
        Route::get('/{id}', [PlanController::class, 'show']);
        Route::put('/{id}', [PlanController::class, 'update']);
        Route::post('/{id}/archive', [PlanController::class, 'archive']);
        Route::delete('/{id}', [PlanController::class, 'destroy']);
    });
