<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\BranchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Branches (FR-STN)
|--------------------------------------------------------------------------
| Branches of the authenticated owner's workspace; the default branch cannot
| be deleted and a branch that still holds stations cannot be either.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/branches')
    ->group(function (): void {
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
        Route::put('/{branch}', [BranchController::class, 'update']);
        Route::delete('/{branch}', [BranchController::class, 'destroy']);
    });
