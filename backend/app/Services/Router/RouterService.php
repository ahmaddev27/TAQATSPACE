<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use App\Support\Router\RouterKeyHasher;

/**
 * Owner-facing router management. Keys are generated here and returned in
 * plaintext exactly once; only their hash is persisted.
 */
class RouterService
{
    /** How long the replaced key keeps working after a rotation. */
    public const KEY_GRACE_MINUTES = 60;

    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Workspace $workspace, string $name): RouterWithKey
    {
        $plainKey = RouterKeyHasher::generate();

        $router = Router::query()->create([
            'workspace_id' => $workspace->getKey(),
            'name' => $name,
            'key_hash' => RouterKeyHasher::hash($plainKey),
            'status' => RouterStatus::Pending,
        ]);

        $this->audit->log('network.router.created', $router, changes: ['name' => $name]);

        return new RouterWithKey($router, $plainKey);
    }

    /**
     * Replace the router's key. The old hash stays valid for the grace period
     * so the router can pick up the new key without going offline.
     */
    public function rotateKeyHash(Router $router, int $graceMinutes = self::KEY_GRACE_MINUTES): RouterWithKey
    {
        $plainKey = RouterKeyHasher::generate();

        $router->update([
            'previous_key_hash' => $router->key_hash,
            'previous_key_expires_at' => now()->addMinutes($graceMinutes),
            'key_hash' => RouterKeyHasher::hash($plainKey),
        ]);

        $this->audit->log('network.router.key_rotated', $router);

        return new RouterWithKey($router, $plainKey);
    }

    public function disable(Router $router): Router
    {
        $router->update(['status' => RouterStatus::Disabled]);

        $this->audit->log('network.router.disabled', $router);

        return $router;
    }

    public function delete(Router $router): void
    {
        $this->audit->log('network.router.deleted', $router, changes: ['name' => $router->name]);

        $router->delete();
    }
}
