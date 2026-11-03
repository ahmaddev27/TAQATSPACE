<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A router sent a plain-text sync body that does not follow the wire format.
 */
final class SyncWireException extends RuntimeException
{
    public static function atLine(int $line, string $reason): self
    {
        return new self("Line {$line}: {$reason}.");
    }
}
