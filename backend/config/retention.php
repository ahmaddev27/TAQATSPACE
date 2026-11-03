<?php

declare(strict_types=1);

/*
 * Retention policy for network data (day counts). Sessions (24 months minimum)
 * and attendance_daily (permanent) are accounting data and are never pruned.
 */
return [
    // Rows deleted per statement, so a cleanup never holds a long lock on the sync tables.
    'batch_size' => 5000,

    // A month counts as closed this many days after it ended (until M6 closes months explicitly).
    'month_close_grace_days' => 90,

    // Raw events are deleted this many days after their month closed.
    'raw_events_days_after_close' => 90,

    'snapshot_chunks_days' => 7,

    'router_api_logs_days' => 14,
];
