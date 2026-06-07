<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOnboardingRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\RegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Role-selection onboarding for SSO-provisioned accounts.
 *
 * A user who signed in via "Sign in with Taqat" lands here with no chosen role.
 * They pick freelancer vs workspace-owner and submit the role-specific data; the
 * account is then classified and routed to the matching next destination.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
    ) {}

    public function complete(CompleteOnboardingRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user = $this->registration->completeOnboarding($user, $request->validated());

        return ApiResponse::success([
            'user' => new UserResource($user),
            'role' => $user->role->value,
            // Owners await admin approval; freelancers are active immediately.
            'next' => $user->role === UserRole::WorkspaceOwner ? 'pending' : 'dashboard',
        ], __('messages.onboarding_completed'));
    }
}
