<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subscriber's month. Derived data, written in bulk by StatsAggregator.
 *
 * @property int $id
 * @property string $workspace_id
 * @property string $subscriber_id
 * @property CarbonInterface $month
 * @property int $attendance_days
 * @property string|null $attendance_rate
 * @property int $active_actual_minutes
 * @property int $day_span_minutes
 * @property int $hour_bucket_hours
 * @property int $half_hour_slots
 * @property int $bytes_in
 * @property int $bytes_out
 * @property CarbonInterface $first_activity_at
 * @property CarbonInterface $last_activity_at
 */
class StatsMonthlySubscriber extends Model
{
    use BelongsToWorkspace;

    protected $table = 'stats_monthly_subscriber';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'subscriber_id',
        'month',
        'attendance_days',
        'attendance_rate',
        'active_actual_minutes',
        'day_span_minutes',
        'hour_bucket_hours',
        'half_hour_slots',
        'bytes_in',
        'bytes_out',
        'first_activity_at',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'attendance_days' => 'integer',
            'attendance_rate' => 'decimal:2',
            'active_actual_minutes' => 'integer',
            'day_span_minutes' => 'integer',
            'hour_bucket_hours' => 'integer',
            'half_hour_slots' => 'integer',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'first_activity_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }
}
