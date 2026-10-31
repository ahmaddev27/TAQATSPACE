<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class OpsRouteRemovedTest extends TestCase
{
    public function test_unauthenticated_cache_clear_route_no_longer_exists(): void
    {
        $this->get('/ops/clear-cache-9f3a2c')->assertNotFound();
    }

    public function test_root_route_still_responds(): void
    {
        $this->get('/')->assertOk();
    }
}
