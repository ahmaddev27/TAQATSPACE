<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SessionEndReason: string
{
    use HasValues;

    case Logout = 'logout';
    case Timeout = 'timeout';
    case SnapshotMissing = 'snapshot_missing';
    case Reboot = 'reboot';
    case Gap = 'gap';

    /** Endings that were inferred, so a real logout that arrives late may still correct them. */
    public function isInferred(): bool
    {
        return in_array($this, [self::Timeout, self::SnapshotMissing], true);
    }
}
