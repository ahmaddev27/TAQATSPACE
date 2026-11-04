<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    /** @use HasFactory<\Database\Factories\AnnouncementFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'title',
        'body',
        'type',
        'published_at',
        'expires_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---- Query scopes (T055) ----

    /**
     * Only announcements that have a publish moment in the past (i.e. not drafts).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now());
    }

    /**
     * Published announcements that have not yet expired (null expiry = never expires).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->published()
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopeForWorkspace(Builder $query, string $workspaceId): void
    {
        $query->where('workspace_id', $workspaceId);
    }
}
