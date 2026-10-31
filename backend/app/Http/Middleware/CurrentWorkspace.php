<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use App\Support\Tenancy\CurrentWorkspaceResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the request's workspace into the tenancy context. Requests that cannot
 * be tied to a workspace are rejected. Pair with `auth:sanctum` (or the router).
 */
final class CurrentWorkspace
{
    public function __construct(
        private readonly CurrentWorkspaceResolver $resolver,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->resolver->resolve($request);

        if ($workspace === null) {
            return ApiResponse::message(__('messages.no_workspace_context'), 403);
        }

        $this->context->set($workspace);

        return $next($request);
    }
}
