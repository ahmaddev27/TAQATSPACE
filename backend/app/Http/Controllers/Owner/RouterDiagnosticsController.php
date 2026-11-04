<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\ResolvesOwnedRouter;
use App\Http\Requests\Owner\ListRouterLogsRequest;
use App\Http\Resources\RouterApiLogResource;
use App\Repositories\Eloquent\RouterApiLogRepository;
use App\Services\Router\Diagnostics\DiagnosticCheck;
use App\Services\Router\RouterDiagnosticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouterDiagnosticsController extends Controller
{
    use ResolvesOwnedRouter;

    public function __construct(
        private readonly RouterApiLogRepository $logs,
        private readonly RouterDiagnosticsService $diagnostics,
    ) {}

    /** GET /api/workspace/routers/{router}/logs — owner only; the router's request log, newest first. */
    public function logs(ListRouterLogsRequest $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $paginator = $this->logs->paginate($target, $request->validated());

        return ApiResponse::success([
            'logs' => RouterApiLogResource::collection($paginator->items())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** POST /api/workspace/routers/{router}/diagnose — owner only; the deep check (cached for 15 s). */
    public function diagnose(Request $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $checks = array_map(fn (DiagnosticCheck $check): array => $check->toArray(), $this->diagnostics->run($target));

        return ApiResponse::success(['checks' => $checks]);
    }
}
