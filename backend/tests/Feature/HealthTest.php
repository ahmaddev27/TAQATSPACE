<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_redis_health_endpoint_never_errors(): void
    {
        // Redis is optional locally/CI — the endpoint must degrade gracefully.
        $this->getJson('/api/health/redis')->assertOk();
    }
}
