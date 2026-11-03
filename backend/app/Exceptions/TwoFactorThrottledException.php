<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Too many wrong two-factor codes; the caller maps this to HTTP 429. */
class TwoFactorThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many two-factor attempts.');
    }
}
