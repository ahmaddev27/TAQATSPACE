<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service profile management for the authenticated user, regardless of
 * role (admin / freelancer / workspace owner). Each user can only ever read or
 * mutate their OWN account — the identity comes from the bearer token, never a
 * route parameter, so no extra authorization is required.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly FileUploadService $files,
    ) {}

    /** Return the authenticated user's own profile. */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()),
        );
    }

    /**
     * Update editable profile fields (+ optional avatar) for the current user.
     * Email is intentionally read-only here: changing it would require
     * re-verification, which is out of scope for inline profile editing.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $attributes = $request->safe()->only(['name', 'phone', 'gender', 'specialty', 'bio']);

        if ($request->hasFile('avatar')) {
            $attributes['avatar'] = $this->files->upload(
                $request->file('avatar'),
                "avatars/{$user->id}",
                (string) config('filesystems.media', 'public'),
                'public',
            );
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }

        return ApiResponse::success(
            new UserResource($user->refresh()),
            __('messages.profile_updated'),
        );
    }

    /** Change the current user's password after confirming the current one. */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Credentials for SSO-provisioned accounts live at the IdP, not here.
        // Defense in depth: reject the request even if the UI exposed the form.
        if ($user->sso_sub !== null) {
            return ApiResponse::error(__('messages.password_change_sso'), 403);
        }

        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            return ApiResponse::error(__('messages.current_password_incorrect'), 422, [
                'current_password' => [__('messages.current_password_incorrect')],
            ]);
        }

        $user->update([
            'password' => Hash::make((string) $request->input('new_password')),
        ]);

        return ApiResponse::success(
            new UserResource($user->refresh()),
            __('messages.password_changed'),
        );
    }
}
