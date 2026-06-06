<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * Normalised, validated input for a broadcast, shared by the admin and owner
 * compose flows. The FormRequest produces this so the service never touches the
 * raw HTTP payload.
 */
final class BroadcastRequestData
{
    /**
     * @param  list<'email'|'sms'>  $channels  the channels to send on (at least one)
     * @param  'all'|'segment'|'specific'  $audience
     * @param  array{role?: ?string, status?: ?string}  $segment  segment filters (admin: role/status; owner: subscription status)
     * @param  list<string>  $userIds  selected recipient ids when audience = specific
     */
    public function __construct(
        public readonly array $channels,
        public readonly string $audience,
        public readonly array $segment,
        public readonly array $userIds,
        public readonly ?string $subject,
        public readonly string $body,
    ) {}

    public function wantsEmail(): bool
    {
        return in_array('email', $this->channels, true);
    }

    public function wantsSms(): bool
    {
        return in_array('sms', $this->channels, true);
    }
}
