<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriberSegment;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subscriber's loyalty and strength. Derived data, written in bulk by ScoringService.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $subscriber_id
 * @property string $loyalty_score
 * @property string $strength_score
 * @property SubscriberSegment $segment
 * @property CarbonInterface $computed_at
 * @property array<string, mixed>|null $components
 */
class SubscriberScore extends Model
{
    use BelongsToWorkspace, HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'subscriber_id',
        'loyalty_score',
        'strength_score',
        'segment',
        'computed_at',
        'components',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loyalty_score' => 'decimal:2',
            'strength_score' => 'decimal:2',
            'segment' => SubscriberSegment::class,
            'computed_at' => 'datetime',
            'components' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }
}
