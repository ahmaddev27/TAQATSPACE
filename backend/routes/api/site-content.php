<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SiteContentController as AdminSiteContentController;
use App\Http\Controllers\SiteContentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site-wide Content CMS — public read + admin manage
|--------------------------------------------------------------------------
| Generic, bilingual, partial-override content (site info, FAQ, About,
| How-it-works) stored as keyed `site_settings` rows. The public site reads
| a key here; a Super Admin edits it via the admin endpoints. The set of
| editable keys is whitelisted in App\Services\SiteContentService::KEYS.
*/

// Public read.
Route::get('/content/{key}', [SiteContentController::class, 'show']);

// Admin manage.
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_content'])
    ->prefix('admin/content')
    ->group(function (): void {
        Route::get('/{key}', [AdminSiteContentController::class, 'show']);
        Route::put('/{key}', [AdminSiteContentController::class, 'update']);
    });
