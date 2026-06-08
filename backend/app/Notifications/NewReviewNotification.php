<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Review;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a workspace owner when a member leaves a new review, so feedback on
 * their space surfaces without them having to check the reviews page.
 */
class NewReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly Review $review,
        public readonly string $memberName,
    ) {}

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $name = $this->workspace->name;
        $rating = $this->review->rating;

        return (new MailMessage)
            ->subject('تقييم جديد لمساحتك / New workspace review')
            ->line("ترك {$this->memberName} تقييمًا جديدًا ({$rating}/5) لمساحتك \"{$name}\".")
            ->line("{$this->memberName} left a new {$rating}/5 review for your workspace \"{$name}\".");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_review',
            'workspace_id' => $this->workspace->id,
            'review_id' => $this->review->id,
            'rating' => $this->review->rating,
            'member_name' => $this->memberName,
        ];
    }
}
