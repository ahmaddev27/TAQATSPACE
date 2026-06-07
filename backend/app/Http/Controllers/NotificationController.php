<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const PER_PAGE = 15;

    /**
     * GET /api/notifications — the authenticated user's notifications, newest
     * first, optionally filtered to unread. Unread total is exposed in meta.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $request->boolean('unread')
            ? $user->unreadNotifications()
            : $user->notifications();

        $perPage = (int) $request->integer('per_page', self::PER_PAGE);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->paginate($perPage);

        return ApiResponse::success([
            'notifications' => NotificationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * PUT /api/notifications/read — mark specific notifications (ids[]) or all
     * of the user's notifications as read.
     */
    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'ids' => ['required_without:all', 'array', 'min:1'],
            'ids.*' => ['uuid'],
        ]);

        $user = $request->user();

        if ($request->boolean('all')) {
            $user->unreadNotifications->markAsRead();

            return ApiResponse::message(__('messages.all_notifications_marked_read'));
        }

        $user->unreadNotifications()
            ->whereIn('id', $validated['ids'])
            ->get()
            ->markAsRead();

        return ApiResponse::message(__('messages.notifications_marked_read'));
    }

    /**
     * DELETE /api/notifications/{id} — delete one of the user's notifications.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return ApiResponse::error(__('messages.notification_not_found'), 404);
        }

        $notification->delete();

        return ApiResponse::message(__('messages.notification_deleted'));
    }
}
