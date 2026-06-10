<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin inbox for public "Contact us" submissions: list, mark read, delete.
 * Gated by the messaging permission.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $messages = ContactMessage::query()->latest()->paginate($perPage);

        return ApiResponse::success([
            'messages' => ContactMessageResource::collection($messages->items()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'unread' => ContactMessage::query()->where('is_read', false)->count(),
            ],
        ]);
    }

    public function markRead(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->forceFill(['is_read' => true])->save();

        return ApiResponse::success(
            new ContactMessageResource($contactMessage),
            __('messages.contact_marked_read'),
        );
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return ApiResponse::success(null, __('messages.contact_deleted'));
    }
}
