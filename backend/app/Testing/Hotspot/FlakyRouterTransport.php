<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

/** Wraps a transport with an on/off switch that models the router's uplink. */
final class FlakyRouterTransport implements RouterTransport
{
    private bool $online = true;

    private bool $armed = false;

    private ?int $poisonedSeq = null;

    public function __construct(private readonly RouterTransport $inner) {}

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    /** The first request that carries events is refused with 422 from now on, like a batch the server cannot parse. */
    public function rejectNextEventBatch(): void
    {
        $this->armed = true;
    }

    public function post(string $routerCode, string $apiKey, string $body): TransportResponse
    {
        if (! $this->online) {
            throw new TransportException('The uplink is down.');
        }

        if ($this->isPoisoned($body)) {
            return new TransportResponse(422, 'The batch was refused.');
        }

        return $this->inner->post($routerCode, $apiKey, $body);
    }

    private function isPoisoned(string $body): bool
    {
        if ($this->armed && $this->poisonedSeq === null && preg_match('/^E\|(\d+)\|/m', $body, $first) === 1) {
            $this->poisonedSeq = (int) $first[1];
        }

        return $this->poisonedSeq !== null && preg_match('/^E\|'.$this->poisonedSeq.'\|/m', $body) === 1;
    }
}
