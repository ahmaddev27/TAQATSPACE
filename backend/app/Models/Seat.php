<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeatKind;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Observers\SeatObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([SeatObserver::class])]
class Seat extends Model
{
    /** @use HasFactory<\Database\Factories\SeatFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'seat_number',
        'type',
        'status',
        'assigned_member_id',
        'notes',
        'zone',
        'kind',
        'capacity',
        'is_private',
        'branch_id',
        'parent_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SeatType::class,
            'status' => SeatStatus::class,
            'kind' => SeatKind::class,
            'capacity' => 'integer',
            'is_private' => 'boolean',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Seat, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_member_id');
    }
}
