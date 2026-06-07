<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\ExpenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Workspace Expenses (mini management module)
|--------------------------------------------------------------------------
| Track operating expenses for the authenticated owner's workspace. All
| actions are scoped to the owner's workspace — foreign expenses are never
| listed or mutated.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/expenses')
    ->group(function (): void {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::put('/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
    });
