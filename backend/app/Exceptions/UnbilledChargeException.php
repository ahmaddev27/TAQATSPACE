<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A rejected unbilled-charge operation. Renders itself as the standard API
 * error envelope so controllers stay free of try/catch.
 */
class UnbilledChargeException extends RuntimeException
{
    public static function notOpen(): self
    {
        return new self(__('messages.unbilled_charge_not_open'));
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 422);
    }
}
