<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Support\Router\RouterKeyHasher;
use PHPUnit\Framework\TestCase;

class RouterKeyHasherTest extends TestCase
{
    public function test_generated_keys_are_long_and_unique(): void
    {
        $a = RouterKeyHasher::generate();
        $b = RouterKeyHasher::generate();

        $this->assertSame(48, strlen($a));
        $this->assertNotSame($a, $b);
    }

    public function test_hash_is_sha256_and_never_the_plaintext(): void
    {
        $hash = RouterKeyHasher::hash('secret');

        $this->assertSame(hash('sha256', 'secret'), $hash);
        $this->assertNotSame('secret', $hash);
    }

    public function test_matches_accepts_only_the_right_key(): void
    {
        $hash = RouterKeyHasher::hash('secret');

        $this->assertTrue(RouterKeyHasher::matches('secret', $hash));
        $this->assertFalse(RouterKeyHasher::matches('other', $hash));
    }
}
