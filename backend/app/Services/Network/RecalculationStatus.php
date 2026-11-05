<?php

declare(strict_types=1);

namespace App\Services\Network;

/** Where a workspace's statistics recalculation stands; kept in the cache between requests. */
final class RecalculationStatus
{
    public const IDLE = 'idle';

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /**
     * @param  array{days: int, subscriber_months: int}|null  $written
     */
    public function __construct(
        public readonly string $state,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $requestedAt = null,
        public readonly ?string $finishedAt = null,
        public readonly ?array $written = null,
    ) {}

    public static function idle(): self
    {
        return new self(self::IDLE);
    }

    public static function queued(string $from, string $to): self
    {
        return new self(self::QUEUED, $from, $to, now()->toIso8601String());
    }

    public function running(): self
    {
        return new self(self::RUNNING, $this->from, $this->to, $this->requestedAt);
    }

    /** @param array{days: int, subscriber_months: int} $written */
    public function completed(array $written): self
    {
        return new self(self::COMPLETED, $this->from, $this->to, $this->requestedAt, now()->toIso8601String(), $written);
    }

    public function failed(): self
    {
        return new self(self::FAILED, $this->from, $this->to, $this->requestedAt, now()->toIso8601String());
    }

    /** True while the owner should keep polling. */
    public function isActive(): bool
    {
        return $this->state === self::QUEUED || $this->state === self::RUNNING;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'from' => $this->from,
            'to' => $this->to,
            'requested_at' => $this->requestedAt,
            'finished_at' => $this->finishedAt,
            'written' => $this->written,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['state'] ?? self::IDLE),
            $data['from'] ?? null,
            $data['to'] ?? null,
            $data['requested_at'] ?? null,
            $data['finished_at'] ?? null,
            $data['written'] ?? null,
        );
    }
}
