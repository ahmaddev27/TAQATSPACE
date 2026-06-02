<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

trait HasValues
{
    /**
     * All backing values of the enum.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
