<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->computedStatus(),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Lifecycle state derived from publish/expiry timestamps:
     *  - draft:   no publish time, or scheduled for the future
     *  - expired: published but past its expiry
     *  - live:    published and not expired
     */
    private function computedStatus(): string
    {
        $now = Carbon::now();

        if ($this->published_at === null || $this->published_at->isAfter($now)) {
            return 'draft';
        }

        if ($this->expires_at !== null && $this->expires_at->isBefore($now)) {
            return 'expired';
        }

        return 'live';
    }
}
