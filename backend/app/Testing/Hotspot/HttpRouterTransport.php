<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Posts over real HTTP, for load tests against a running server. */
final class HttpRouterTransport implements RouterTransport
{
    public function __construct(private readonly string $baseUrl) {}

    public function post(string $routerCode, string $apiKey, string $body): TransportResponse
    {
        $url = rtrim($this->baseUrl, '/')."/api/v1/hotspot/{$routerCode}/sync";

        try {
            $response = Http::withHeaders(['X-API-Key' => $apiKey])
                ->timeout(30)
                ->withBody($body, 'text/plain')
                ->post($url);
        } catch (ConnectionException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return new TransportResponse($response->status(), $response->body());
    }
}
