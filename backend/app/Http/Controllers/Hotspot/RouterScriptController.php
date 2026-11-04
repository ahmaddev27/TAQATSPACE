<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hotspot;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Repositories\Eloquent\RouterScriptRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Plain-text endpoints for RouterOS `/tool fetch`, which only reads the status
 * code and the body: reachability check and rendered script download.
 */
class RouterScriptController extends Controller
{
    public function __construct(private readonly RouterScriptRepository $scripts) {}

    /** GET /api/v1/hotspot/ping — public; proves the host and its certificate are reachable. */
    public function ping(): Response
    {
        return $this->plainText('ok', 200);
    }

    /**
     * GET /api/v1/hotspot/{code}/scripts/{name} — authenticated by the `router`
     * middleware; serves the script rendered for the calling router.
     */
    public function show(Request $request, string $code, string $name): Response
    {
        /** @var Router $router */
        $router = $request->attributes->get('router');
        $file = $this->scripts->resolve($name);

        if ($file === null) {
            return $this->plainText('Unknown script.', 404);
        }

        return $this->plainText($this->scripts->renderFor($router, $file), 200);
    }

    private function plainText(string $body, int $status): Response
    {
        return response($body, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
