<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Chat\StoreChatAttachmentRequest;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
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
     * POST /api/chat/notify — raise an in-app + push alert for a chat message
     * the caller just delivered over Firestore, so the recipient is notified
     * even with their chat page closed. The recipient must be one of the
     * caller's chat-able contacts (prevents notifying arbitrary users).
     */
    public function notify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'string'],
            'preview' => ['nullable', 'string', 'max:140'],
        ]);

        $sender = $request->user();
        $recipientId = $validated['recipient_id'];

        $recipient = User::query()->find($recipientId);

        if ($recipient === null) {
            return ApiResponse::success(['notified' => false]);
        }

        // Notify only a genuine counterpart: a contact of the sender, or an admin
        // (whom owners/freelancers don't list but may legitimately reply to).
        $isContact = collect($this->contacts->contactsFor($sender))
            ->contains(static fn (array $c): bool => $c['id'] === $recipientId);

        if (! $isContact && ! $recipient->isAdmin()) {
            return ApiResponse::success(['notified' => false]);
        }

        $recipient->notify(
            new NewChatMessageNotification($sender, (string) ($validated['preview'] ?? '')),
        );

        return ApiResponse::success(['notified' => true]);
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

        return ApiResponse::success($meta, null, 201);
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

        // Only resolve keys under the chat attachments prefix, and never let a
        // crafted "../" escape it.
        if (! str_starts_with($path, ChatAttachmentService::DIRECTORY.'/') || str_contains($path, '..')) {
            return ApiResponse::error(__('messages.chat_attachment_not_found'), 404);
        }

        $url = $attachments->url($path);

        if ($url === null) {
            return ApiResponse::error(__('messages.chat_attachment_not_found'), 404);
        }

        return ApiResponse::success(['url' => $url]);
    }
}
