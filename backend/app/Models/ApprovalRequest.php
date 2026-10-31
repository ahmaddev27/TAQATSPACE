<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending (or decided) change a workspace owner asked for. Deliberately not
 * tenant-scoped: platform admins read requests across every workspace.
 *
 * @property string $id
 * @property string $workspace_id
 * @property ApprovalType $type
 * @property array<string, mixed> $payload
 * @property ApprovalStatus $status
 */
class ApprovalRequest extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'type',
        'payload',
        'status',
        'requested_by',
        'decided_by',
        'decision_reason',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApprovalType::class,
            'status' => ApprovalStatus::class,
            'payload' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }
}
