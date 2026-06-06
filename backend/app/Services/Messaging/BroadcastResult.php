<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * Immutable summary of a broadcast dispatch: how many recipients were queued
 * per channel and how many were skipped for lack of the required contact field
 * (no email / no phone). Returned to the composer so the UI can report exactly
 * what happened.
 */
final class BroadcastResult
{
    public function __construct(
        public readonly int $queuedEmail = 0,
        public readonly int $queuedSms = 0,
        public readonly int $skippedEmail = 0,
        public readonly int $skippedSms = 0,
    ) {}

    /**
     * @return array{queued: array{email: int, sms: int, total: int}, skipped: array{email: int, sms: int, total: int}}
     */
    public function toArray(): array
    {
        return [
            'queued' => [
                'email' => $this->queuedEmail,
                'sms' => $this->queuedSms,
                'total' => $this->queuedEmail + $this->queuedSms,
            ],
            'skipped' => [
                'email' => $this->skippedEmail,
                'sms' => $this->skippedSms,
                'total' => $this->skippedEmail + $this->skippedSms,
            ],
        ];
    }
}
