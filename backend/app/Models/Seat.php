<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SeatType::class,
            'status' => SeatStatus::class,
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_member_id');
    }
}
