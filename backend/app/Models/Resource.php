<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bookable/physical resource owned by a workspace (meeting room, private
 * office, equipment, parking…), tracked with a quantity and availability.
 *
 * @property string $workspace_id
 */
class Resource extends Model
{
    /** @use HasFactory<\Database\Factories\ResourceFactory> */
    use HasFactory, HasUuids;

    protected $table = 'workspace_resources';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'quantity',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'status' => ResourceStatus::class,
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
