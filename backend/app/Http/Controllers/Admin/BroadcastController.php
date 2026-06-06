<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBroadcastRequest;
use App\Services\Messaging\BroadcastConfigException;
use App\Services\Messaging\BroadcastService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Super Admin compose-and-broadcast: send an email and/or SMS to all users, a
 * role/status segment, or specific users — through Taqat's platform accounts.
 * Behind auth:sanctum + role.admin. The send itself is queued; this returns a
 * queued/skipped summary immediately.
 */
class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastService $broadcasts,
    ) {}

    /**
     * POST /api/admin/messaging/broadcast
     */
    public function store(AdminBroadcastRequest $request): JsonResponse
    {
        try {
            $result = $this->broadcasts->broadcastFromPlatform($request->toData());
        } catch (BroadcastConfigException $e) {
            return ApiResponse::error(__($e->translationKey), 422);
        }

        return ApiResponse::success($result->toArray(), __('messages.broadcast_queued'));
    }
}
