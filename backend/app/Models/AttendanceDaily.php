<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscriber's presence on one workspace-local day. High volume (BIGINT
 * key); written in bulk by AttendanceRebuilder, never edited by hand.
 *
 * @property int $id
 * @property string $workspace_id
 * @property string $subscriber_id
 * @property CarbonInterface $date
 * @property int $slots_bitmap
 * @property int $active_actual_minutes
 * @property int $day_span_minutes
 * @property int $hour_bucket_hours
 * @property int $half_hour_slots
 * @property CarbonInterface $first_seen_at
 * @property CarbonInterface $last_seen_at
 * @property bool $has_gap
 */
class AttendanceDaily extends Model
{
    use BelongsToWorkspace;

    protected $table = 'attendance_daily';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'subscriber_id',
        'date',
        'slots_bitmap',
        'active_actual_minutes',
        'day_span_minutes',
        'hour_bucket_hours',
        'half_hour_slots',
        'first_seen_at',
        'last_seen_at',
        'has_gap',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'slots_bitmap' => 'integer',
            'active_actual_minutes' => 'integer',
            'day_span_minutes' => 'integer',
            'hour_bucket_hours' => 'integer',
            'half_hour_slots' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'has_gap' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }
}
