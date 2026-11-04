<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Kinds of numbered documents; each has its own gap-free sequence per workspace. */
enum DocumentType: string
{
    use HasValues;

    case Invoice = 'invoice';
    case Receipt = 'receipt';
}
