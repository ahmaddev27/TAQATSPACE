<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkspaceFeature;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One feature flag row of a workspace. Read through
 * {@see \App\Services\FeatureFlagService}, which caches the per-workspace map.
 *
 * @property string $workspace_id
 * @property WorkspaceFeature $feature
 * @property bool $enabled
 */
class WorkspaceFeatureFlag extends Model
{
    use HasUuids;

    protected $table = 'workspace_features';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'feature', 'enabled'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'feature' => WorkspaceFeature::class,
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
