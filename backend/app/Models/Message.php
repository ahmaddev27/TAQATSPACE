<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'workspace_id',
        'type',
        'content',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ---- Scopes ----

    /**
     * Limit to messages belonging to a single workspace.
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * The direct conversation between two participants, in either direction.
     * Broadcast rows (receiver_id = null) are intentionally excluded.
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeThreadWith(Builder $query, string $participantA, string $participantB): Builder
    {
        return $query->where(static function (Builder $inner) use ($participantA, $participantB): void {
            $inner->where(static function (Builder $forward) use ($participantA, $participantB): void {
                $forward->where('sender_id', $participantA)->where('receiver_id', $participantB);
            })->orWhere(static function (Builder $reverse) use ($participantA, $participantB): void {
                $reverse->where('sender_id', $participantB)->where('receiver_id', $participantA);
            });
        });
    }
}
