<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHourPackageRequest;
use App\Http\Resources\HourPackageResource;
use App\Models\HourPackage;
use App\Services\Hours\HourPackageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HourPackageController extends Controller
{
    public function __construct(private readonly HourPackageService $packages) {}

    /** GET /api/admin/hour-packages — the full catalog, archived included. */
    public function index(): JsonResponse
    {
        return ApiResponse::success(HourPackageResource::collection($this->packages->all()));
    }

    /** POST /api/admin/hour-packages */
    public function store(StoreHourPackageRequest $request): JsonResponse
    {
        $package = $this->packages->create($request->validated());

        return ApiResponse::success(new HourPackageResource($package), __('messages.hour_package_created'), 201);
    }

    /** PUT /api/admin/hour-packages/{hour_package} */
    public function update(StoreHourPackageRequest $request, HourPackage $hourPackage): JsonResponse
    {
        $package = $this->packages->update($hourPackage, $request->validated());

        return ApiResponse::success(new HourPackageResource($package), __('messages.hour_package_updated'));
    }

    /** DELETE /api/admin/hour-packages/{hour_package} — archives, never deletes. */
    public function destroy(HourPackage $hourPackage): JsonResponse
    {
        $package = $this->packages->archive($hourPackage);

        return ApiResponse::success(new HourPackageResource($package), __('messages.hour_package_archived'));
    }
}
