<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Models\RouterApiLog;
use App\Models\Workspace;
use App\Services\Hotspot\SyncWireParser;
use App\Services\Router\RouterSecurityService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a MikroTik router: the `X-API-Key` header must match the
 * router's current key (or the previous one during its grace period) AND the
 * router must belong to the workspace named by the `{code}` route segment.
 * The resolved router is stashed on the request as `router`; every request
 * that reached a known router is written to `router_api_logs` on terminate.
 */
final class AuthenticateRouter
{
    public function __construct(private readonly RouterSecurityService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('router_started_at', microtime(true));

        $apiKey = $request->header('X-API-Key');
        $router = is_string($apiKey) && $apiKey !== '' ? Router::findByKey($apiKey) : null;

        // Unknown key: nothing to attribute the request to, so no api log row.
        if ($router === null) {
            return ApiResponse::error(__('messages.router_unauthorized'), 401);
        }

        $request->attributes->set('router', $router);

        if (! $this->mayUse($router, (string) $request->route('code'))) {
            Log::warning('Router rejected', [
                'router_id' => $router->getKey(),
                'status' => $router->status->value,
                'ip' => $request->ip(),
            ]);

            return ApiResponse::error(__('messages.router_forbidden'), 403);
        }

        $serial = $this->presentedSerial($request);

        if ($this->serialRefused($request, $router, $serial)) {
            $this->security->lock($router, $serial);

            return ApiResponse::error(__('messages.router_forbidden'), 403);
        }

        return $next($request);
    }

    /**
     * A bound router must send its serial when it syncs. Script downloads have
     * no body to carry one and return nothing secret, so refusing them for that
     * would lock a router on its first script update.
     */
    private function serialRefused(Request $request, Router $router, ?string $serial): bool
    {
        if ($serial === null && $request->isMethodSafe()) {
            return false;
        }

        return ! $this->security->serialMatches($router, $serial);
    }

    public function terminate(Request $request, Response $response): void
    {
        $router = $request->attributes->get('router');

        if (! $router instanceof Router) {
            return;
        }

        $status = $response->getStatusCode();

        RouterApiLog::query()->create([
            'router_id' => $router->getKey(),
            'path' => '/'.$request->path(),
            'response_code' => $status,
            'duration_ms' => $this->durationMs($request),
            'body_size' => strlen((string) $request->getContent()),
            'error_message' => $status >= 400 ? $this->errorMessage($response) : null,
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * findByKey also returns disabled/locked routers, so they are refused here;
     * and a key is only good for the workspace its router belongs to.
     */
    private function mayUse(Router $router, string $code): bool
    {
        if (in_array($router->status, [RouterStatus::Disabled, RouterStatus::Locked], true)) {
            return false;
        }

        $workspaceId = Workspace::query()->where('code', $code)->value('id');

        return $workspaceId !== null && (string) $workspaceId === (string) $router->workspace_id;
    }

    private function presentedSerial(Request $request): ?string
    {
        $serial = $request->getContentTypeFormat() === 'txt'
            ? SyncWireParser::serialOf($request->getContent())
            : $request->input('serial');

        return is_string($serial) && $serial !== '' ? $serial : null;
    }

    private function durationMs(Request $request): int
    {
        $started = (float) $request->attributes->get('router_started_at', microtime(true));

        return (int) round((microtime(true) - $started) * 1000);
    }

    private function errorMessage(Response $response): ?string
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) && is_string($decoded['message'] ?? null) ? $decoded['message'] : null;
    }
}
