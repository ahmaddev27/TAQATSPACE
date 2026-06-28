<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\Workspace;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds a workspace-branded email: the workspace's own logo + name in the
 * header and as the sender name, instead of the platform's branding. Used by the
 * member-facing invoice notifications so a space's emails read in its own name.
 */
trait RendersWorkspaceMail
{
    /**
     * @param  list<string>  $lines
     */
    protected function workspaceMail(?Workspace $workspace, string $subject, array $lines): MailMessage
    {
        $name = $workspace?->name ?? (string) config('app.name');

        return (new MailMessage)
            ->from((string) config('mail.from.address'), $name)
            ->subject($subject)
            ->view('emails.workspace-message', [
                'workspaceName' => $name,
                'logoUrl' => $workspace?->logoUrl(),
                'lines' => $lines,
            ]);
    }
}
