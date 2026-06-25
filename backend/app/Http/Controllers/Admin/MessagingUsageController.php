<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMessagingUsageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Read-only admin view of broadcast messaging usage per workspace — who sends
 * through Taqat's platform quota vs their own accounts, and how many messages.
 */
final class MessagingUsageController extends Controller
{
    public function __construct(private readonly AdminMessagingUsageService $usage) {}

    /**
     * GET /api/admin/messaging/usage
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->usage->summary());
    }
}
