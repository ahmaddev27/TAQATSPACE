<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hotspot;

use App\Exceptions\InstallTokenException;
use App\Http\Controllers\Controller;
use App\Services\Router\RouterInstallerService;
use Illuminate\Http\Response;

class RouterInstallerController extends Controller
{
    public function __construct(private readonly RouterInstallerService $installer) {}

    /**
     * GET /api/v1/hotspot/install/{token}.rsc — public, token-gated, single use.
     *
     * Plain text on every outcome: the caller is RouterOS `/tool fetch`, which
     * only looks at the status code (404 unknown, 410 used or expired).
     */
    public function show(string $token): Response
    {
        try {
            $script = $this->installer->render($token);
        } catch (InstallTokenException $e) {
            return $this->plainText($e->getMessage(), $e->status);
        }

        return $this->plainText($script, 200);
    }

    private function plainText(string $body, int $status): Response
    {
        return response($body, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
