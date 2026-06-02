<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Single, consistent JSON envelope for the whole API.
 *
 * Success: { "data": ..., "message"?: ... }
 * Error:   { "message": ..., "errors"?: { field: [..] } }
 */
final class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = [];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
