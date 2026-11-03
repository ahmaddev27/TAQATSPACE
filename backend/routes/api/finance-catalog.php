<?php

declare(strict_types=1);

use App\Http\Controllers\Finance\CatalogCategoryController;
use App\Http\Controllers\Finance\CatalogItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance v2 — catalog (categories and items)
|--------------------------------------------------------------------------
| Behind the `finance_v2` flag (404 when off). Owners and cashiers read;
| changes need the owner or the `pos_manage_products` grant. DELETE archives.
*/
Route::middleware(['auth:sanctum', 'workspace.current', 'feature:finance_v2,hidden'])
    ->prefix('workspace/finance/catalog')
    ->group(function (): void {
        Route::get('/categories', [CatalogCategoryController::class, 'index']);
        Route::post('/categories', [CatalogCategoryController::class, 'store']);
        Route::post('/categories/reorder', [CatalogCategoryController::class, 'reorder']);
        Route::put('/categories/{id}', [CatalogCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CatalogCategoryController::class, 'destroy']);

        Route::get('/items', [CatalogItemController::class, 'index']);
        Route::post('/items', [CatalogItemController::class, 'store']);
        Route::put('/items/{id}', [CatalogItemController::class, 'update']);
        Route::delete('/items/{id}', [CatalogItemController::class, 'destroy']);
    });
