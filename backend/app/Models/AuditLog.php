<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One immutable audit entry. Written only through
 * {@see \App\Services\Audit\AuditLogger}; updates and deletes are refused.
 *
 * @property int $id
 * @property string|null $workspace_id
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'reason',
        'changes',
        'ip',
        'request_id',
    ];

    protected static function booted(): void
    {
        $refuse = static function (): never {
            throw new LogicException('Audit log entries are append-only.');
        };

        static::updating($refuse);
        static::deleting($refuse);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'changes' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
