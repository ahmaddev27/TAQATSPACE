<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\Network\AttendanceRebuilder;
use Illuminate\Console\Command;

/**
 * Three ways in: no options rebuilds the days marked since the last run (the
 * five-minute schedule), --recent is the nightly pass over the last days of
 * every workspace, and --workspace with --from/--to repairs an explicit range.
 */
class RebuildAttendance extends Command
{
    protected $signature = 'attendance:rebuild
        {--workspace= : Workspace id; requires --from and --to}
        {--from= : First local date, Y-m-d}
        {--to= : Last local date, Y-m-d, inclusive}
        {--recent : Rebuild the last two local days of every workspace}';

    protected $description = 'Rebuild attendance_daily from hotspot sessions.';

    public function handle(AttendanceRebuilder $rebuilder): int
    {
        if ($this->option('workspace') !== null) {
            return $this->rebuildRange($rebuilder);
        }

        $rows = $this->option('recent') ? $rebuilder->rebuildRecent() : $rebuilder->rebuildAffected();
        $this->info("Wrote {$rows} attendance row(s).");

        return self::SUCCESS;
    }

    private function rebuildRange(AttendanceRebuilder $rebuilder): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');

        if (! $this->isDate($from) || ! $this->isDate($to) || $from > $to) {
            $this->error('--from and --to must be Y-m-d dates with --from not after --to.');

            return self::INVALID;
        }

        $workspace = Workspace::query()->find($this->option('workspace'));

        if ($workspace === null) {
            $this->error('Workspace not found.');

            return self::FAILURE;
        }

        $this->info("Wrote {$rebuilder->rebuild($workspace, $from, $to)} attendance row(s).");

        return self::SUCCESS;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
