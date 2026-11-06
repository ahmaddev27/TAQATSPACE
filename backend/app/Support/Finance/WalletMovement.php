<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\WalletEntryType;

/**
 * One movement to write on a wallet: `$amount` is the positive size, and the
 * stored sign follows the type. `$relatedType` / `$relatedId` name the document
 * or entry it belongs to; `$requestKey` makes a repeated request a no-op.
 */
final readonly class WalletMovement
{
    public function __construct(
        public WalletEntryType $type,
        public string $amount,
        public ?string $reason = null,
        public ?string $relatedType = null,
        public ?string $relatedId = null,
        public ?string $requestKey = null,
        public ?string $method = null,
    ) {}
}
