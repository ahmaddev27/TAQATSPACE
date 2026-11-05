<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Previewed batches can be committed; failed ones had row errors; committed ones are final. */
enum ImportStatus: string
{
    use HasValues;

    case Previewed = 'previewed';
    case Committed = 'committed';
    case Failed = 'failed';
}
