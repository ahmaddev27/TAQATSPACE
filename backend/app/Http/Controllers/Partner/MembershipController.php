<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerMembershipService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Partner-facing read of a student's membership status on Work.
 */
final class MembershipController extends Controller
{
    public function __construct(private readonly PartnerMembershipService $memberships) {}

    public function show(string $identifier): JsonResponse
    {
        return ApiResponse::success($this->memberships->forIdentifier($identifier));
    }
}
