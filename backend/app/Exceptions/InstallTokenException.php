<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** An install token that cannot be redeemed: unknown (404) or spent/expired (410). */
final class InstallTokenException extends RuntimeException
{
    private function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function unknown(): self
    {
        return new self('Install token not found.', 404);
    }

    public static function gone(): self
    {
        return new self('Install token already used or expired.', 410);
    }
}
