<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Document lifecycle of an invoice: editable draft, frozen once issued, void when cancelled. */
enum DocStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';
}
