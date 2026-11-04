<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\User;
use App\Models\Workspace;
use RuntimeException;

/** What earlier steps created, so later steps can hang their data off it. */
final class DemoContext
{
    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Workspace> */
    private array $workspaces = [];

    /** @param  string  $passwordHash  Already hashed; the plain password never reaches a step. */
    public function __construct(public readonly string $passwordHash) {}

    public function addUser(string $key, User $user): void
    {
        $this->users[$key] = $user;
    }

    public function user(string $key): User
    {
        return $this->users[$key] ?? throw new RuntimeException("Demo user [{$key}] has not been seeded.");
    }

    public function addWorkspace(string $key, Workspace $workspace): void
    {
        $this->workspaces[$key] = $workspace;
    }

    public function workspace(string $key): Workspace
    {
        return $this->workspaces[$key] ?? throw new RuntimeException("Demo workspace [{$key}] has not been seeded.");
    }

    /** @return array<string, User> */
    public function users(): array
    {
        return $this->users;
    }

    /** @return array<string, Workspace> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }
}
