<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * A single broadcast recipient's contact fields, decoupled from the Eloquent
 * model so the dispatch loop only carries what a send actually needs.
 */
final class Recipient
{
    public function __construct(
        public readonly ?string $email,
        public readonly ?string $phone,
    ) {}
}
