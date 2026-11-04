<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PlanType;
use App\Enums\SeatType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRequest extends Model
{
    /** @use HasFactory<\Database\Factories\BookingRequestFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'workspace_id',
        'preferred_seat_type',
        'plan_type',
        'message',
        'status',
        'reviewed_by',
        'rejection_reason',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_seat_type' => SeatType::class,
            'plan_type' => PlanType::class,
            'status' => BookingStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
