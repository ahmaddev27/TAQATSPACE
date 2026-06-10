<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\AdminUserDetailResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $users,
    ) {}

    /**
     * GET /api/admin/users — filtered, paginated user directory.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['role', 'status', 'search', 'per_page']);

        $paginator = $this->users->paginate($filters);

        return ApiResponse::success(UserResource::collection($paginator)->response()->getData(true));
    }

    /**
     * GET /api/admin/users/{user} — full profile with role-specific detail
     * (a freelancer's subscriptions, an owner's workspace).
     */
    public function show(User $user): JsonResponse
    {
        $detail = $this->users->findForDetail($user);

        return ApiResponse::success(new AdminUserDetailResource($detail));
    }

    /**
     * PUT /api/admin/users/{user}/status — activate/suspend an account.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        // Staff accounts (incl. the acting super-admin themselves) are never
        // suspendable from the user directory.
        if ($user->isAdmin()) {
            return ApiResponse::error(__('messages.cannot_modify_admin'), 403);
        }

        $updated = $this->users->changeStatus($user, $request->status());

        return ApiResponse::success(new UserResource($updated), __('messages.user_status_updated'));
    }
}
