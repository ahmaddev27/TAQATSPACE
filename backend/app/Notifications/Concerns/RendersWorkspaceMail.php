<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\Workspace;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

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
                // Inline (CID) bytes are embedded in the email so the logo shows
                // reliably regardless of disk URL reachability or link expiry; the
                // resolved URL is a fallback when embedding isn't possible.
                'logo' => $this->workspaceLogoBytes($workspace),
                'logoUrl' => $workspace?->logoUrl(),
                'lines' => $lines,
            ]);
    }

    /**
     * Read the workspace logo's raw bytes + mime for inline email embedding, or
     * null when the workspace has no stored logo file.
     *
     * @return array{data: string, mime: string}|null
     */
    private function workspaceLogoBytes(?Workspace $workspace): ?array
    {
        $path = $workspace?->logo_path;

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk((string) config('filesystems.media', 'public'));

        if (! $disk->exists($path)) {
            return null;
        }

        return [
            'data' => $disk->get($path),
            'mime' => $disk->mimeType($path) ?: 'image/png',
        ];
    }
}
