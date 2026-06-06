<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\LandingController as AdminLandingController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing Page CMS — public read + admin manage
|--------------------------------------------------------------------------
| The public landing page reads its (bilingual, partial-override) content
| here; a Super Admin edits it via the admin endpoints.
*/

// Public read.
Route::get('/landing', [LandingController::class, 'show']);

// Admin manage.
Route::middleware(['auth:sanctum', 'role.admin'])
    ->prefix('admin/landing')
    ->group(function (): void {
        Route::get('/', [AdminLandingController::class, 'show']);
        Route::put('/', [AdminLandingController::class, 'update']);
        Route::post('/images', [AdminLandingController::class, 'uploadImage']);
    });
