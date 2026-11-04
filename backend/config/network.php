<?php

declare(strict_types=1);

return [
    'sessions' => [
        // An open session nobody has vouched for this long is closed at its last known moment.
        'timeout_minutes' => (int) env('NETWORK_SESSION_TIMEOUT_MINUTES', 15),

        // Consecutive applied snapshots a device may be missing from before its session is closed.
        'snapshot_miss_limit' => 2,

        // How far back a late logout may still correct a session that was closed by inference.
        'late_event_window_minutes' => 120,

        // Per-router lock that serialises session building between workers.
        'lock_seconds' => 30,
        'lock_wait_seconds' => 20,
    ],
];
