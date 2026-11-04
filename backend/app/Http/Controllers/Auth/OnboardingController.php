<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOnboardingRequest;
use App\Http\Requests\Auth\OnboardingSeatsRequest;
use App\Http\Resources\SeatResource;
use App\Http\Resources\UserResource;
use App\Services\Auth\RegistrationService;
use App\Services\SeatService;
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

    /**
     * Generate the workspace's physical seats during onboarding, from the
     * per-type counts the (still pending) owner chooses. Runs outside the active
     * owner seat API so the owner can lay out their space before approval.
     */
    public function seats(OnboardingSeatsRequest $request, SeatService $seats): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \App\Models\Workspace $workspace */
        $workspace = $user->workspace;

        $created = $seats->setupFromCounts($workspace, $request->validated()['seats']);

        return ApiResponse::success([
            'seats' => SeatResource::collection($created),
        ], __('messages.onboarding_seats_created'));
    }
}
