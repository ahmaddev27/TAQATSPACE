<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the request locale before validation runs so error messages
        // (and every other translated string) come back in the caller's language.
        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'role.freelancer' => \App\Http\Middleware\EnsureFreelancer::class,
            'role.owner' => \App\Http\Middleware\EnsureWorkspaceOwner::class,
            'role.admin' => \App\Http\Middleware\EnsureAdmin::class,
            'can.manage_admins' => \App\Http\Middleware\EnsureCanManageAdmins::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
