<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Chat\StoreChatAttachmentRequest;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatContactService;
use App\Services\Firebase\FirebaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Realtime-chat support endpoints. Thin by design: minting a Firebase custom
 * token (so the SPA can authenticate against Firestore) and listing the
 * authenticated user's chat-able contacts. All business logic is delegated.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly FirebaseService $firebase,
        private readonly ChatContactService $contacts,
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

    /**
     * GET /api/chat/contacts — the authenticated user's chat-able contacts.
     *
     * Role-scoped: an owner gets their workspace members; a freelancer gets
     * their workspace owner(s). Returns `[{ id, name, workspace_id }]`.
     */
    public function contacts(Request $request): JsonResponse
    {
        $contacts = $this->contacts->contactsFor($request->user());

        return ApiResponse::success(['contacts' => $contacts]);
    }

    /**
     * POST /api/chat/attachments — store a chat attachment on S3 and return its
     * metadata. The client embeds `{ path, name, type, size }` in the Firestore
     * message; the file itself never touches Firestore.
     */
    public function uploadAttachment(
        StoreChatAttachmentRequest $request,
        ChatAttachmentService $attachments,
    ): JsonResponse {
        $meta = $attachments->store($request->file('file'), (string) $request->user()->id);

        return ApiResponse::success($meta, 201);
    }

    /**
     * GET /api/chat/attachments/url?path=… — resolve a fresh viewable URL for a
     * stored attachment (a short-lived signed S3 URL), so older messages stay
     * accessible without persisting an expiring URL in Firestore.
     */
    public function attachmentUrl(
        Request $request,
        ChatAttachmentService $attachments,
    ): JsonResponse {
        $path = (string) $request->query('path', '');

        // Only resolve keys under the chat attachments prefix.
        if (! str_starts_with($path, ChatAttachmentService::DIRECTORY.'/')) {
            return ApiResponse::error(__('messages.chat_attachment_not_found'), 404);
        }

        $url = $attachments->url($path);

        if ($url === null) {
            return ApiResponse::error(__('messages.chat_attachment_not_found'), 404);
        }

        return ApiResponse::success(['url' => $url]);
    }
}
