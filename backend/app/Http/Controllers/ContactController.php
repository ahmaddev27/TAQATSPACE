<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\StoreContactRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\NewContactMessageNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    /**
     * POST /api/contact — store a public contact-form message and notify admins.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $message = ContactMessage::create($request->validated());

        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->where('status', UserStatus::Active->value)
            ->get();

        Notification::send($admins, new NewContactMessageNotification($message));

        return ApiResponse::success(null, __('messages.contact_sent'), 201);
    }
}
