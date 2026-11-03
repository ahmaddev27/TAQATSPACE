<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

/**
 * A validated sync batch as sent by a router.
 */
final readonly class SyncPayload
{
    /**
     * @param  list<SyncEvent>  $events
     */
    public function __construct(
        public string $batchId,
        public string $bootId,
        public int $seq,
        public int $routerNow,
        public ?string $serial,
        public ?string $routerosVersion,
        public ?string $scriptVersion,
        public array $events,
        public ?SyncSnapshot $snapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated request input.
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            batchId: (string) $data['batch_id'],
            bootId: (string) $data['boot_id'],
            seq: (int) $data['seq'],
            routerNow: (int) $data['now'],
            serial: $data['serial'] ?? null,
            routerosVersion: $data['routeros_version'] ?? null,
            scriptVersion: $data['script_version'] ?? null,
            events: array_map(SyncEvent::fromValidated(...), array_values($data['events'] ?? [])),
            snapshot: isset($data['snapshot']) ? SyncSnapshot::fromValidated($data['snapshot']) : null,
        );
    }
}
