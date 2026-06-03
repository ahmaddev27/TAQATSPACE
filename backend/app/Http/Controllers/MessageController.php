<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Message\BroadcastMessageRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\MessageThreadResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
    ) {}

    /**
     * GET /api/workspace/messages — owner inbox: one thread per member with the
     * last message and unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success(['threads' => []]);
        }

        $threads = $this->messages->listThreads($workspace, (string) $request->user()->id);

        return ApiResponse::success([
            'threads' => MessageThreadResource::collection($threads),
        ]);
    }

    /**
     * GET /api/workspace/messages/{member} — full direct thread with one member.
     */
    public function show(Request $request, User $member): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null || ! $this->messages->isActiveMember($workspace->id, (string) $member->id)) {
            return ApiResponse::error('Member not found.', 404);
        }

        $thread = $this->messages->thread(
            $workspace,
            (string) $request->user()->id,
            (string) $member->id,
        );

        return ApiResponse::success([
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'avatar' => $member->avatar,
            ],
            'messages' => MessageResource::collection($thread),
        ]);
    }

    /**
     * POST /api/workspace/messages — owner sends a direct message to a member.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error('You have no workspace.', 404);
        }

        $receiverId = (string) $request->validated()['receiver_id'];

        if (! $this->messages->isActiveMember($workspace->id, $receiverId)) {
            return ApiResponse::error('Recipient is not an active member of your workspace.', 422);
        }

        $message = $this->messages->sendDirect(
            $workspace,
            (string) $request->user()->id,
            $receiverId,
            (string) $request->validated()['content'],
        );

        return ApiResponse::success(new MessageResource($message), 'Message sent.', 201);
    }

    /**
     * POST /api/workspace/messages/broadcast — owner broadcasts to all active
     * members of the workspace.
     */
    public function broadcast(BroadcastMessageRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error('You have no workspace.', 404);
        }

        $message = $this->messages->broadcast(
            $workspace,
            (string) $request->user()->id,
            (string) $request->validated()['content'],
        );

        return ApiResponse::success(new MessageResource($message), 'Broadcast sent.', 201);
    }

    /**
     * PUT /api/workspace/messages/read — owner or member marks received
     * messages in a conversation as read.
     */
    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isOwner()) {
            return $this->markReadAsOwner($request);
        }

        return $this->markReadAsMember($request);
    }

    private function markReadAsOwner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'with' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error('You have no workspace.', 404);
        }

        $updated = $this->messages->markThreadRead(
            $workspace,
            (string) $request->user()->id,
            (string) $validated['with'],
        );

        return ApiResponse::success(['marked_read' => $updated], 'Messages marked as read.');
    }

    private function markReadAsMember(Request $request): JsonResponse
    {
        $workspace = $this->messages->memberActiveWorkspace((string) $request->user()->id);

        if ($workspace === null) {
            return ApiResponse::error('No active membership found.', 404);
        }

        $updated = $this->messages->markThreadRead(
            $workspace,
            (string) $request->user()->id,
            (string) $workspace->owner_id,
        );

        return ApiResponse::success(['marked_read' => $updated], 'Messages marked as read.');
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
    }
}
