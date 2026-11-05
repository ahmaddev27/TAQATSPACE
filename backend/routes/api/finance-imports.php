<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\ImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — XLSX import (catalog items and withdrawals)
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Preview validates a file and
| writes nothing; commit writes every row in one transaction or none. The
| import grant follows what is imported (`finance_items` / `finance_withdrawals`).
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])
    ->prefix('workspace/finance/imports')
    ->group(function (): void {
        Route::post('/preview', [ImportController::class, 'preview'])->middleware('throttle:20,1');
        Route::post('/{id}/commit', [ImportController::class, 'commit'])->whereUuid('id');
        Route::get('/template/{type}', [ImportController::class, 'template']);
    });
