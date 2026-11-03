<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Workspace;

/**
 * One module's slice of a workspace reset. Later modules implement this and tag
 * the class `workspace.reset` in a service provider; the resetter picks it up
 * without any change here. Implementations must constrain every query by the
 * workspace id and may only delete operational data, never identity or config.
 */
interface WorkspaceResetStep
{
    public function key(): string;

    /** Runs inside the reset transaction; throw to roll the whole reset back. */
    public function reset(Workspace $workspace): void;

    /**
     * Storage paths (on the media disk) to remove once the transaction has
     * committed. Collected before deletion so the rows are still readable.
     *
     * @return list<string>
     */
    public function filePaths(Workspace $workspace): array;
}
