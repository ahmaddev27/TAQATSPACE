<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RouterCommandStatus: string
{
    use HasValues;

    /** Queued, not yet sent to the router. */
    case Requested = 'requested';

    /** Sent in a sync reply, waiting for the router to acknowledge it. */
    case Delivered = 'delivered';
    case Acknowledged = 'acknowledged';

    /** The router rejected it, or it was never acknowledged. */
    case Failed = 'failed';
    case Expired = 'expired';

    /** @return list<string> statuses a command can still leave */
    public static function openValues(): array
    {
        return [self::Requested->value, self::Delivered->value];
    }
}
