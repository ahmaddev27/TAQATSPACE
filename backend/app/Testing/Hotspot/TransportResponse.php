<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

final readonly class TransportResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {}

    /** RouterOS `/tool fetch` fails on anything but 2xx, which keeps the batch for the next cycle. */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
