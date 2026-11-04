<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Models\Workspace;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'Router '.fake()->unique()->numerify('####'),
            'key_hash' => RouterKeyHasher::hash(RouterKeyHasher::generate()),
            'status' => RouterStatus::Pending->value,
            'last_seq' => 0,
        ];
    }

    public function withKey(string $plain): static
    {
        return $this->state(fn (): array => ['key_hash' => RouterKeyHasher::hash($plain)]);
    }
}
