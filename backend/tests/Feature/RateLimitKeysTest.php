<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Without a prefix, `throttle:N,M` counts per user or IP across every route that
 * uses it, so one action eats another's allowance. An owner using chat could get
 * a 429 on a recalculation, and guests behind one address could lose password
 * reset for an hour after using the contact form.
 */
final class RateLimitKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_numeric_throttle_counts_on_a_key_of_its_own(): void
    {
        $owners = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || preg_match('/^throttle:\d+,\d+(?:,(.+))?$/', $middleware, $match) !== 1) {
                    continue;
                }

                $this->assertArrayHasKey(1, $match, "{$route->uri()} needs a prefix: {$middleware}");
                $this->assertArrayNotHasKey($match[1], $owners, "{$route->uri()} shares the key of ".($owners[$match[1]] ?? ''));

                $owners[$match[1]] = $route->uri();
            }
        }

        $this->assertNotEmpty($owners);
    }

    public function test_using_up_one_limit_leaves_the_others_alone(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/contact')->assertUnprocessable();
        }

        $this->postJson('/api/contact')->assertTooManyRequests();
        $this->postJson('/api/auth/forgot-password')->assertUnprocessable();
    }
}
