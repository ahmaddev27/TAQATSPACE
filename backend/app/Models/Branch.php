<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical location of a workspace. Every workspace has exactly one default branch.
 *
 * @property string $workspace_id
 * @property string|null $default_workspace_id
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'address',
        'is_default',
    ];

    protected static function booted(): void
    {
        // The unique index on this column is what limits a workspace to one default branch.
        // Not `saving`: on create it fires before the trait fills `workspace_id`.
        $syncDefaultMarker = function (Branch $branch): void {
            $branch->default_workspace_id = $branch->is_default ? $branch->workspace_id : null;
        };

        static::creating($syncDefaultMarker);
        static::updating($syncDefaultMarker);
    }

    /** @return HasMany<Seat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
