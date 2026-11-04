<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

/**
 * One event inside a sync batch. `routerAt` is on the router's own clock.
 */
final readonly class SyncEvent
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public int $seq,
        public string $type,
        public int $routerAt,
        public ?string $mac,
        public ?string $ip,
        public ?string $username,
        public ?array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            seq: (int) $data['seq'],
            type: (string) $data['type'],
            routerAt: (int) $data['at'],
            mac: $data['mac'] ?? null,
            ip: $data['ip'] ?? null,
            username: $data['username'] ?? null,
            data: $data['data'] ?? null,
        );
    }
}
