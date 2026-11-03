<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

/** Wraps a transport with an on/off switch that models the router's uplink. */
final class FlakyRouterTransport implements RouterTransport
{
    private bool $online = true;

    public function __construct(private readonly RouterTransport $inner) {}

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    public function post(string $routerCode, string $apiKey, string $body): TransportResponse
    {
        if (! $this->online) {
            throw new TransportException('The uplink is down.');
        }

        return $this->inner->post($routerCode, $apiKey, $body);
    }
}
