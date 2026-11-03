<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A rejected hour-package operation. Renders itself as the standard API error
 * envelope so controllers stay free of try/catch.
 */
class HourPackageException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function purchaseNotRequested(): self
    {
        return new self(__('messages.hour_purchase_not_requested'), 409);
    }

    public static function packageUnavailable(): self
    {
        return new self(__('messages.hour_package_unavailable'));
    }

    public static function negativeBalance(): self
    {
        return new self(__('messages.hour_adjustment_negative_balance'));
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status);
    }
}
