<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Sends the batch through the application's own HTTP kernel, so the real
 * route, middleware, throttle and controller run without a socket.
 */
final class FakeRouterTransport implements RouterTransport
{
    public function post(string $routerCode, string $apiKey, string $body): TransportResponse
    {
        $request = Request::create("/api/v1/hotspot/{$routerCode}/sync", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X_API_KEY' => $apiKey,
        ], $body);

        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return new TransportResponse($response->getStatusCode(), (string) $response->getContent());
    }
}
