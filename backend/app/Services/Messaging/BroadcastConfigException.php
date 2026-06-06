<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use RuntimeException;

/**
 * Raised when a broadcast is requested on a channel whose sending account is not
 * configured (no SMTP host / no SMS credentials). Carries a translation key so
 * the controller can return a clear, localized 422 rather than a 500.
 */
final class BroadcastConfigException extends RuntimeException
{
    public function __construct(
        public readonly string $translationKey,
    ) {
        parent::__construct($translationKey);
    }
}
