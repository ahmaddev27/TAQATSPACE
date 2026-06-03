<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Announcement $announcement,
        public readonly string $workspaceName,
    ) {}

    /**
     * Persisted to the notifications table; also broadcast for live UI updates.
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_announcement',
            'announcement_id' => $this->announcement->id,
            'announcement_type' => $this->announcement->type->value,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'workspace_id' => $this->announcement->workspace_id,
            'workspace_name' => $this->workspaceName,
        ];
    }
}
