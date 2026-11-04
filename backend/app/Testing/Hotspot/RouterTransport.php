<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

/**
 * How a simulated router reaches the sync endpoint. Implementations differ
 * only in the wire they use; the body is always the router's text/plain batch.
 */
interface RouterTransport
{
    /**
     * @throws TransportException when the server cannot be reached at all
     */
    public function post(string $routerCode, string $apiKey, string $body): TransportResponse;
}
