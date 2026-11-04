<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Router;
use App\Models\RouterApiLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouterApiLog>
 */
class RouterApiLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'router_id' => Router::factory(),
            'path' => '/api/router/sync',
            'response_code' => 200,
            'duration_ms' => fake()->numberBetween(5, 300),
            'body_size' => fake()->numberBetween(100, 5000),
            'error_message' => null,
            'request_id' => fake()->uuid(),
        ];
    }
}
