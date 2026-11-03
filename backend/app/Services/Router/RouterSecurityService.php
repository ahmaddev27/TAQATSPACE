<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Enums\RouterStatus;
use App\Exceptions\RouterRotationInProgressException;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Services\Audit\AuditLogger;
use App\Support\Router\RouterKeyHasher;

/**
 * Router cloning protection and key lifecycle: the first sync binds the
 * device serial, a valid key from another serial locks the router until the
 * owner unlocks it, and a key rotation is delivered to the router as a
 * `set-key` command so it never has to be reinstalled. Keys are never logged.
 */
class RouterSecurityService
{
    public function __construct(
        private readonly RouterService $routers,
        private readonly RouterCommandService $commands,
        private readonly AuditLogger $audit,
    ) {}

    /** Binds the serial on the first sync only; later calls never overwrite it. */
    public function bindSerial(Router $router, ?string $serial): void
    {
        if ($serial === null || $router->serial_number !== null) {
            return;
        }

        $bound = Router::query()->withoutWorkspaceScope()
            ->whereKey($router->getKey())
            ->whereNull('serial_number')
            ->update(['serial_number' => $serial]);

        if ($bound === 0) {
            return;
        }

        $router->serial_number = $serial;
        $router->syncOriginalAttribute('serial_number');
        $this->audit->log('network.router.serial_bound', $router, changes: ['serial_number' => $serial]);
    }

    /** True when the router may talk: it is not bound yet, or it presented the bound serial. */
    public function serialMatches(Router $router, ?string $presented): bool
    {
        // Once bound, omitting the serial must not bypass the lock.
        return $router->serial_number === null
            || ($presented !== null && hash_equals($router->serial_number, $presented));
    }

    public function lock(Router $router, ?string $presentedSerial = null): void
    {
        $router->update(['status' => RouterStatus::Locked]);

        $this->audit->log('network.router.locked', $router, changes: [
            'bound_serial' => $router->serial_number,
            'presented_serial' => $presentedSerial,
        ]);
    }

    /** Clears the serial so a replacement device can be bound by its next sync. */
    public function unlock(Router $router): Router
    {
        $router->update([
            'serial_number' => null,
            'status' => $router->status === RouterStatus::Locked ? RouterStatus::Pending : $router->status,
        ]);

        $this->audit->log('network.router.unlocked', $router);

        return $router;
    }

    /**
     * Issues a new key and queues `set-key`. The old key keeps working for the
     * grace period and is revoked once the router acknowledges the command.
     *
     * @throws RouterRotationInProgressException
     */
    public function rotateKey(Router $router): RouterWithKey
    {
        // A second rotation would overwrite the only key the router still knows.
        if ($this->rotationPending($router)) {
            throw new RouterRotationInProgressException;
        }

        $rotated = $this->routers->rotateKeyHash($router);
        $this->commands->enqueue($router, RouterCommandVerb::SetKey, ['key' => $rotated->plainKey], RouterService::KEY_GRACE_MINUTES);

        return $rotated;
    }

    /**
     * Drops the previous key once the router confirmed the current one. It
     * costs a query only while a previous key is still waiting for that.
     */
    public function revokePreviousKeyIfConfirmed(Router $router): void
    {
        $router->refresh();

        if ($router->previous_key_hash === null || ! $this->currentKeyAcknowledged($router)) {
            return;
        }

        $router->update(['previous_key_hash' => null, 'previous_key_expires_at' => null]);
        $this->audit->log('network.router.previous_key_revoked', $router);
    }

    private function rotationPending(Router $router): bool
    {
        return $router->previous_key_hash !== null
            && $router->previous_key_expires_at !== null
            && $router->previous_key_expires_at->isFuture();
    }

    private function currentKeyAcknowledged(Router $router): bool
    {
        $command = RouterCommand::query()->withoutWorkspaceScope()
            ->where('router_id', $router->getKey())
            ->where('verb', RouterCommandVerb::SetKey->value)
            ->where('status', RouterCommandStatus::Acknowledged->value)
            ->latest('acknowledged_at')
            ->first();

        $key = $command?->payload['key'] ?? null;

        return is_string($key) && hash_equals($router->key_hash, RouterKeyHasher::hash($key));
    }
}
