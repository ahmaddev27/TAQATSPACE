<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every API request an id (honouring a sane inbound `X-Request-Id`) so
 * audit entries and response headers can be correlated.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $inbound = (string) $request->header('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $inbound) === 1 ? $inbound : (string) Str::uuid();

        $request->attributes->set('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
