<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\MailConfigurator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A broadcast email composed by an admin or workspace owner and delivered to a
 * chosen audience. The subject and body are supplied by the composer; delivery
 * goes through whichever mailer {@see MailConfigurator} configured
 * for the send (the platform account or a workspace's own).
 *
 * The body is plain text entered by the composer; it is HTML-escaped and its
 * line breaks preserved when rendered, so no markup is injected.
 */
class BroadcastMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $subjectLine,
        private readonly string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<div style="white-space:pre-wrap">'.nl2br(e($this->bodyText)).'</div>',
        );
    }
}
