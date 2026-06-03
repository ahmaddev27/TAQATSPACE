<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Public review shape: reviewer first name only (last name masked for privacy),
 * rating, comment, and date.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewer_name' => $this->reviewerFirstName(),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Expose only the reviewer's first name to protect privacy. Falls back to
     * a generic label when the member relation is not loaded or has no name.
     */
    private function reviewerFirstName(): string
    {
        $name = $this->whenLoaded('member', fn (): ?string => $this->member?->name);

        if (! is_string($name) || trim($name) === '') {
            return 'Member';
        }

        return Str::of($name)->trim()->before(' ')->value();
    }
}
