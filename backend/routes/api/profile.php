<?php

declare(strict_types=1);

use App\Http\Controllers\Member\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Freelancer Profile (T035)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role.freelancer'])
    ->prefix('member')
    ->group(function (): void {
        Route::put('/profile', [ProfileController::class, 'update']);
    });
