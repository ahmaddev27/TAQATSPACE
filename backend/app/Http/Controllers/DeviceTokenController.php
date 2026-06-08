<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\DeleteDeviceTokenRequest;
use App\Http\Requests\Notification\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Registration of FCM device tokens for the authenticated user, so push
 * notifications can reach their devices. Thin: all identity comes from the
 * bearer token; tokens always belong to the calling user.
 */
class DeviceTokenController extends Controller
{
    /**
     * POST /api/notifications/device-tokens — register (upsert) the caller's
     * device token. A token is globally unique, so re-registering it (even from
     * a different account) reassigns it to the current user.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
            ],
        );

        return ApiResponse::message(__('messages.device_token_registered'), 201);
    }

    /**
     * DELETE /api/notifications/device-tokens — remove one of the caller's
     * device tokens (e.g. on logout). Scoped to the user so a token can never
     * be deleted on someone else's behalf.
     */
    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        $request->user()
            ->deviceTokens()
            ->where('token', $request->validated()['token'])
            ->delete();

        return ApiResponse::message(__('messages.device_token_removed'));
    }
}
