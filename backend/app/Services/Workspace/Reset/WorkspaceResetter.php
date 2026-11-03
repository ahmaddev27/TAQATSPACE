<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset;

use App\Contracts\WorkspaceResetStep;
use App\Models\Workspace;
use InvalidArgumentException;

/**
 * Registry of per-module reset steps, in registration order. Registering a step
 * under an existing key replaces it (used by tests to swap in a fake).
 */
class WorkspaceResetter
{
    /** @var array<string, WorkspaceResetStep> */
    private array $steps = [];

    /**
     * @param  iterable<WorkspaceResetStep>  $steps
     */
    public function __construct(iterable $steps = [])
    {
        foreach ($steps as $step) {
            $this->register($step);
        }
    }

    public function register(WorkspaceResetStep $step): void
    {
        if ($step->key() === '') {
            throw new InvalidArgumentException('A workspace reset step needs a non-empty key.');
        }

        $this->steps[$step->key()] = $step;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->steps);
    }

    /**
     * Runs every step; the caller owns the transaction.
     *
     * @return list<string> Storage paths to delete after commit.
     */
    public function reset(Workspace $workspace): array
    {
        $paths = [];

        foreach ($this->steps as $step) {
            $paths = [...$paths, ...$step->filePaths($workspace)];
            $step->reset($workspace);
        }

        return array_values(array_unique($paths));
    }
}
