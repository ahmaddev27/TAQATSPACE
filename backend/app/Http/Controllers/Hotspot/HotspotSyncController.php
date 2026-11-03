<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hotspot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hotspot\SyncRequest;
use App\Models\Router;
use App\Services\Hotspot\HotspotSyncService;
use Illuminate\Http\Response;

class HotspotSyncController extends Controller
{
    public function __construct(private readonly HotspotSyncService $sync) {}

    /**
     * Receive a router's sync batch. Authenticated by the `router` middleware;
     * replies in plain text, not the JSON envelope.
     */
    public function store(SyncRequest $request, string $code): Response
    {
        /** @var Router $router */
        $router = $request->attributes->get('router');

        return response($this->sync->handle($router, $request->payload()), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
