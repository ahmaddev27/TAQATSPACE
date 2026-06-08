<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Firebase\FirebaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issues Firebase **custom tokens** so the SPA can authenticate against
 * Firestore for realtime chat. Thin: the uid and role come straight from the
 * authenticated user.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly FirebaseService $firebase,
    ) {}

    /**
     * POST /api/chat/token — mint a Firebase custom token for the caller.
     *
     * Returns 503 with a localized message when Firebase is not configured, so
     * the client can degrade gracefully instead of receiving a server error.
     */
    public function token(Request $request): JsonResponse
    {
        if (! $this->firebase->isConfigured()) {
            return ApiResponse::error(__('messages.chat_not_configured'), 503);
        }

        $user = $request->user();

        $token = $this->firebase->mintCustomToken($user->id, [
            'role' => $user->role->value,
        ]);

        if ($token === null) {
            return ApiResponse::error(__('messages.chat_token_failed'), 503);
        }

        return ApiResponse::success([
            'token' => $token,
            'uid' => $user->id,
        ]);
    }
}
