<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One workspace-local day of a workspace. Derived data, written in bulk by
 * StatsAggregator and never edited by hand.
 *
 * @property int $id
 * @property string $workspace_id
 * @property CarbonInterface $date
 * @property int $active_members
 * @property int $dau
 * @property int $active_minutes
 * @property int $hour_bucket_hours
 * @property int $bytes_in
 * @property int $bytes_out
 * @property int $peak_concurrent
 * @property int|null $peak_hour
 * @property list<int>|null $hourly_presence
 * @property bool $has_gap
 * @property int $gap_minutes
 */
class StatsDailyWorkspace extends Model
{
    use BelongsToWorkspace;

    protected $table = 'stats_daily_workspace';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'date',
        'active_members',
        'dau',
        'active_minutes',
        'hour_bucket_hours',
        'bytes_in',
        'bytes_out',
        'peak_concurrent',
        'peak_hour',
        'hourly_presence',
        'has_gap',
        'gap_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'active_members' => 'integer',
            'dau' => 'integer',
            'active_minutes' => 'integer',
            'hour_bucket_hours' => 'integer',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'peak_concurrent' => 'integer',
            'peak_hour' => 'integer',
            'hourly_presence' => 'array',
            'has_gap' => 'boolean',
            'gap_minutes' => 'integer',
        ];
    }
}
