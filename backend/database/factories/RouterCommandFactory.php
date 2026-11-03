<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Models\Router;
use App\Models\RouterCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouterCommand>
 */
class RouterCommandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'router_id' => Router::factory(),
            'workspace_id' => fn (array $attributes): string => Router::query()->withoutGlobalScopes()->findOrFail($attributes['router_id'])->workspace_id,
            'verb' => RouterCommandVerb::DisableUser->value,
            'payload' => ['username' => 'user1'],
            'status' => RouterCommandStatus::Requested->value,
            'expires_at' => now()->addHour(),
        ];
    }
}
