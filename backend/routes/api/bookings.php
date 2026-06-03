<?php

declare(strict_types=1);

use App\Http\Controllers\BookingRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Booking requests (T031, T036, T037)
|--------------------------------------------------------------------------
*/

// Freelancer submits a booking request.
Route::middleware(['auth:sanctum', 'role.freelancer'])->group(function (): void {
    Route::post('/booking-requests', [BookingRequestController::class, 'store']);
});

// Owner reviews their workspace's booking queue.
Route::middleware(['auth:sanctum', 'role.owner'])->prefix('workspace/booking-requests')->group(function (): void {
    Route::get('/', [BookingRequestController::class, 'index']);
    Route::put('/{bookingRequest}', [BookingRequestController::class, 'update']);
});
