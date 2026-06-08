<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| TEMPORARY ops helper — REMOVE after use.
|
| Clears the framework caches so a fresh `.env` change (e.g. the SSO logout URL
| TAQAT_SSO_END_SESSION_URL) takes effect without shell access. Hit it once after
| editing `.env`; if the returned `sso_end_session_endpoint` is the real URL (not
| "EMPTY"), the value is now loaded and single logout will work. The obscure path
| is a light guard, not real security — delete this route once logout is verified.
|
|   GET  api.staging.taqat.space/ops/clear-cache-9f3a2c
*/
Route::get('ops/clear-cache-9f3a2c', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');

    return response()->json([
        'ok' => true,
        'cleared' => ['config', 'cache', 'route', 'view'],
        'sso_end_session_endpoint' => config('services.taqat_sso.end_session_endpoint') ?: 'EMPTY',
    ]);
});
