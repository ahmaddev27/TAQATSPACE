<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Workspace;

/**
 * Holds the workspace the current request (or job) operates on. Registered as
 * a scoped binding so it never leaks between requests in long-lived workers.
 */
final class CurrentWorkspaceContext
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function clear(): void
    {
        $this->workspace = null;
    }

    public function workspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?string
    {
        return $this->workspace?->getKey();
    }

    public function has(): bool
    {
        return $this->workspace !== null;
    }
}
