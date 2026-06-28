<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PartnerClient;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server auth for the partner API. Authenticates the caller by the
 * `X-Api-Key` header against an active `partner_clients` row and stashes the
 * resolved client on the request as `partner` for downstream handlers.
 */
final class AuthenticatePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key');

        $partner = is_string($apiKey) && $apiKey !== ''
            ? PartnerClient::findByApiKey($apiKey)
            : null;

        if ($partner === null) {
            return ApiResponse::error(__('messages.partner_unauthorized'), 401);
        }

        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
