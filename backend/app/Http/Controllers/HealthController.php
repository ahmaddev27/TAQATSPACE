<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /**
     * Liveness probe used by uptime monitoring.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Redis is optional locally — report state without ever failing the request.
     */
    public function redis(): JsonResponse
    {
        try {
            Redis::connection()->ping();
            $state = 'up';
        } catch (Throwable) {
            $state = 'unavailable';
        }

        return ApiResponse::success([
            'redis' => $state,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
