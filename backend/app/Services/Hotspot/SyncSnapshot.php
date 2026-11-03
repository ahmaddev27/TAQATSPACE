<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

/**
 * One chunk of a router snapshot inside a sync batch.
 */
final readonly class SyncSnapshot
{
    public function __construct(
        public string $id,
        public int $index,
        public int $total,
        public int $routerTakenAt,
        public string $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            index: (int) $data['index'],
            total: (int) $data['total'],
            routerTakenAt: (int) $data['taken_at'],
            data: (string) $data['data'],
        );
    }
}
