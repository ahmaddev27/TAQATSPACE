<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Models\Router;

/**
 * A router together with its freshly generated plaintext key. The key lives
 * only in this value object and must be shown to the owner once.
 */
final readonly class RouterWithKey
{
    public function __construct(
        public Router $router,
        public string $plainKey,
    ) {}
}
