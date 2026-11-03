<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\WorkspaceFeature;
use App\Services\FeatureFlagService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a module's endpoints (403) unless the request's workspace has the
 * named feature enabled, e.g. `feature:network`. Pair with `auth:sanctum`.
 * `feature:finance_v2,hidden` answers 404 instead, so a module that is off
 * looks like it does not exist.
 */
final class EnsureFeatureEnabled
{
    public function __construct(
        private readonly CurrentWorkspaceResolver $resolver,
        private readonly FeatureFlagService $features,
    ) {}

    public function handle(Request $request, Closure $next, string $feature, string $mode = 'forbidden'): Response
    {
        $flag = WorkspaceFeature::from($feature);
        $workspace = $this->resolver->resolve($request);

        if ($workspace === null || ! $this->features->isEnabled($workspace->id, $flag)) {
            return $mode === 'hidden'
                ? ApiResponse::message(__('messages.feature_not_found'), 404)
                : ApiResponse::message(__('messages.feature_disabled'), 403);
        }

        return $next($request);
    }
}
