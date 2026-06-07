<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\RegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
    ) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $result = $this->registration->register(
            $request->safe()->except(['license_file', 'id_document', 'password_confirmation']),
            $request->file('license_file'),
            $request->file('id_document'),
        );

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'role' => $result['user']->role->value,
        ], 'Registration successful.', 201);
    }
}
