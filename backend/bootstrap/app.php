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
            \App\Http\Middleware\AssignRequestId::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // A challenge-only (`2fa-pending`) token may reach nothing but the 2FA challenge.
        $middleware->api(append: [\App\Http\Middleware\RejectPendingTwoFactor::class]);

        $middleware->alias([
            'role.freelancer' => \App\Http\Middleware\EnsureFreelancer::class,
            'role.owner' => \App\Http\Middleware\EnsureWorkspaceOwner::class,
            'role.admin' => \App\Http\Middleware\EnsureAdmin::class,
            // Fine-grained admin gate: `can.permission:<permission>`.
            'can.permission' => \App\Http\Middleware\EnsureHasAdminPermission::class,
            // Binds the request's workspace into the tenancy context (403 if none).
            'workspace.current' => \App\Http\Middleware\CurrentWorkspace::class,
            // Step-up: users with 2FA must have verified a code in the last 10 minutes.
            '2fa.recent' => \App\Http\Middleware\RequireRecentTwoFactor::class,
            // Per-workspace module gate: `feature:<network|finance_v2|gsg>` (403 when off).
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            // Server-to-server partner auth via `X-Api-Key`.
            'partner' => \App\Http\Middleware\AuthenticatePartner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
