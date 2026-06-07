<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * A user's self-reported gender. Optional everywhere: a null column means
 * "unspecified" (e.g. accounts that predate this field, or users who skipped it).
 */
enum Gender: string
{
    use HasValues;

    case Male = 'male';
    case Female = 'female';
}
