<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The previous key rotation has not been confirmed by the router yet.
 */
final class RouterRotationInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A key rotation is still waiting for the router.');
    }
}
