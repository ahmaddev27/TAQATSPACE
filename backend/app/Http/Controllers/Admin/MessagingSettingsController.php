<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestMessagingRequest;
use App\Http\Requests\Admin\UpdateMessagingSettingsRequest;
use App\Services\MessagingSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Super Admin management of Taqat's own (platform) messaging accounts —
 * SMTP email + SMS. Secrets are never returned: responses carry `has_*` flags
 * only. All routes are behind auth:sanctum + role.admin.
 */
class MessagingSettingsController extends Controller
{
    public function __construct(
        private readonly MessagingSettingsService $messaging,
    ) {}

    /**
     * GET /api/admin/settings/messaging — masked platform config.
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->messaging->getPlatform());
    }

    /**
     * PUT /api/admin/settings/messaging — upsert the platform config.
     * Secrets are encrypted; blank/omitted secrets preserve the stored value.
     */
    public function update(UpdateMessagingSettingsRequest $request): JsonResponse
    {
        $saved = $this->messaging->updatePlatform($request->messagingData());

        return ApiResponse::success($saved, 'Messaging settings updated.');
    }

    /**
     * POST /api/admin/settings/messaging/test — send a test email/SMS through
     * the platform config. Driver errors are reported, not thrown.
     */
    public function test(TestMessagingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->messaging->sendPlatformTest(
            $validated['channel'],
            $validated['to'],
        );

        return $result['ok']
            ? ApiResponse::message($result['message'])
            : ApiResponse::error($result['message'], 422);
    }
}
