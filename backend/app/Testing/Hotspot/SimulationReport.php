<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

final readonly class SimulationReport
{
    /** Stored event times may differ from the real ones by this much (whole-second rounding). */
    public const CLOCK_TOLERANCE_SECONDS = 1;

    /**
     * @param  array<int, int>  $statusCounts  HTTP status => number of requests
     */
    public function __construct(
        public SimulationScenario $scenario,
        public int $routers,
        public int $batchesSent,
        public int $batchesDelivered,
        public int $unreachable,
        public array $statusCounts,
        public int $eventsGenerated,
        public int $eventsStored,
        public int $eventsLost,
        public int $duplicatesSent,
        public int $duplicatesStored,
        public int $seqGaps,
        public int $maxClockErrorSeconds,
        public int $commandsQueued,
        public int $commandsAcknowledged,
    ) {}

    public function serverErrors(): int
    {
        return array_sum(array_filter($this->statusCounts, static fn (int $count, int $status): bool => $status >= 500, ARRAY_FILTER_USE_BOTH));
    }

    public function successRate(): float
    {
        return $this->batchesSent === 0 ? 1.0 : $this->batchesDelivered / $this->batchesSent;
    }

    /** Failures caused only by the scenario itself (an outage) do not count against the run. */
    public function passes(): bool
    {
        return $this->eventsLost === 0
            && $this->duplicatesStored === 0
            && $this->seqGaps === 0
            && $this->serverErrors() === 0
            && $this->maxClockErrorSeconds <= self::CLOCK_TOLERANCE_SECONDS
            && $this->commandsAcknowledged === $this->commandsQueued;
    }

    /** @return array<string, string|int|float> */
    public function toArray(): array
    {
        return [
            'scenario' => $this->scenario->value,
            'routers' => $this->routers,
            'batches sent' => $this->batchesSent,
            'batches delivered' => $this->batchesDelivered,
            'success rate' => round($this->successRate() * 100, 1).'%',
            'unreachable (outage)' => $this->unreachable,
            'server errors (5xx)' => $this->serverErrors(),
            'events generated' => $this->eventsGenerated,
            'events stored' => $this->eventsStored,
            'events lost' => $this->eventsLost,
            'duplicates sent' => $this->duplicatesSent,
            'duplicates stored' => $this->duplicatesStored,
            'seq gaps' => $this->seqGaps,
            'max clock error (s)' => $this->maxClockErrorSeconds,
            'commands acknowledged' => "{$this->commandsAcknowledged}/{$this->commandsQueued}",
        ];
    }
}
