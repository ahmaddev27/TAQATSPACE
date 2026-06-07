<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\Admin\AdminResource;
use App\Models\User;
use App\Services\Admin\AdminManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin administration of staff accounts. Every route is gated by
 * `can.manage_admins` (the `manage_admins` permission, held only by
 * super-admins). Controllers stay thin — all rules live in
 * {@see AdminManagementService}.
 */
class AdminManagementController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    /**
     * GET /api/admin/admins — filtered, paginated admin directory.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'per_page']);

        $paginator = $this->admins->paginate($filters);

        return ApiResponse::success(AdminResource::collection($paginator)->response()->getData(true));
    }

    /**
     * POST /api/admin/admins — create a new admin account.
     */
    public function store(StoreAdminRequest $request): JsonResponse
    {
        $admin = $this->admins->create(
            $request->safe()->only(['name', 'email', 'password']),
            $request->adminRole(),
            $request->permissions(),
        );

        return ApiResponse::success(
            new AdminResource($admin),
            __('messages.admin_created'),
            201,
        );
    }

    /**
     * PUT /api/admin/admins/{user} — update an admin's status, role, permissions
     * and/or password. The acting super-admin is passed for self-lockout guards.
     */
    public function update(UpdateAdminRequest $request, User $user): JsonResponse
    {
        $admin = $this->admins->update(
            $user,
            $request->user(),
            $request->newStatus(),
            $request->adminRole(),
            $request->permissions(),
            $request->newPassword(),
        );

        return ApiResponse::success(new AdminResource($admin), __('messages.admin_updated'));
    }

    /**
     * DELETE /api/admin/admins/{user} — deactivate (suspend) an admin account.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $admin = $this->admins->deactivate($user, $request->user());

        return ApiResponse::success(new AdminResource($admin), __('messages.admin_deactivated'));
    }
}
