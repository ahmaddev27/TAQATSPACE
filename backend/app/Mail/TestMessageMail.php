<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A minimal "your SMTP works" email used by the admin messaging-test endpoint.
 * Sent synchronously through whichever mailer {@see \App\Support\MailConfigurator}
 * just configured, so a misconfigured account surfaces an immediate error.
 */
class TestMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $body = 'This is a test email from Taqat to verify your SMTP configuration.',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Taqat — SMTP test message',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>'.e($this->body).'</p>',
        );
    }
}
