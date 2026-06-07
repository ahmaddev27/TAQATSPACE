<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
    ) {}

    public function store(StoreReviewRequest $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $data = $request->validated();
        $workspaceId = (string) $data['workspace_id'];

        if (! $this->reviews->memberCanReview($member, $workspaceId)) {
            return ApiResponse::error(
                __('messages.review_only_subscribed'),
                403,
            );
        }

        if ($this->reviews->alreadyReviewed($member, $workspaceId)) {
            return ApiResponse::error(__('messages.review_already_reviewed'), 409);
        }

        $review = $this->reviews->create($member, $workspaceId, [
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        return ApiResponse::success(
            new ReviewResource($review),
            __('messages.review_submitted'),
            201,
        );
    }

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 50));

        $reviews = $this->reviews->paginateForWorkspace($workspace, $perPage);

        return ApiResponse::success([
            'reviews' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
