<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(
        private readonly FileUploadService $files,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->filled('new_password') && ! Hash::check((string) $request->input('current_password'), $user->password)) {
            return ApiResponse::error('The current password is incorrect.', 422, [
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $attributes = $request->safe()->only(['name', 'phone', 'specialty', 'bio']);

        if ($request->hasFile('avatar')) {
            $attributes['avatar'] = $this->files->upload(
                $request->file('avatar'),
                "avatars/{$user->id}",
                (string) config('filesystems.media', 'public'),
                'public',
            );
        }

        if ($request->filled('new_password')) {
            $attributes['password'] = Hash::make((string) $request->input('new_password'));
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }

        return ApiResponse::success(
            new UserResource($user->refresh()),
            'Profile updated successfully.',
        );
    }
}
