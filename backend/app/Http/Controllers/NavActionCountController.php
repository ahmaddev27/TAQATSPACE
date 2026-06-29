<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NavActionCountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavActionCountController extends Controller
{
    public function __construct(
        private readonly NavActionCountService $counts,
    ) {}

    /**
     * GET /api/nav/action-counts
     *
     * Role-agnostic: returns the "needs your action" badge counts that apply to
     * the authenticated caller. Owners get { bookings, receipts } scoped to their
     * workspace; admins get { workspaces } platform-wide; everyone else gets {}.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->counts->forUser($user));
    }
}
