<?php

declare(strict_types=1);

namespace App\Support\Network;

use App\Models\HotspotSession;
use Carbon\CarbonInterface;

/** Where a session counts as ending, shared by attendance and statistics so both agree on open sessions. */
final class SessionWindow
{
    /** An open session counts up to the last moment the router vouched for it, never beyond now. */
    public static function endOf(HotspotSession $session): CarbonInterface
    {
        if ($session->ended_at !== null) {
            return $session->ended_at;
        }

        $vouched = $session->last_seen_at->greaterThan($session->started_at) ? $session->last_seen_at : $session->started_at;

        return $vouched->greaterThan(now()) ? now() : $vouched;
    }
}
