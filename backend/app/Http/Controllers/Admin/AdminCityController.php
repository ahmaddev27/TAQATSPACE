<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Services\CityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminCityController extends Controller
{
    public function __construct(
        private readonly CityService $cities,
    ) {}

    /**
     * GET /api/admin/cities — all cities with their workspace counts.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CityResource::collection($this->cities->listWithCounts()),
        );
    }

    /**
     * POST /api/admin/cities — create a city.
     */
    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = $this->cities->create($request->validated());

        return ApiResponse::success(
            new CityResource($city),
            __('messages.city_created'),
            201,
        );
    }

    /**
     * PUT /api/admin/cities/{city} — update a city (renames propagate to the
     * denormalized workspace display string).
     */
    public function update(UpdateCityRequest $request, City $city): JsonResponse
    {
        $updated = $this->cities->update($city, $request->validated());

        return ApiResponse::success(
            new CityResource($updated),
            __('messages.city_updated'),
        );
    }

    /**
     * DELETE /api/admin/cities/{city} — remove an unused city, or deactivate it
     * when workspaces still reference it (delete-protection).
     */
    public function destroy(City $city): JsonResponse
    {
        $result = $this->cities->delete($city);

        $message = $result['deleted']
            ? __('messages.city_deleted')
            : __('messages.city_deactivated');

        return ApiResponse::success($result, $message);
    }
}
