<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DiagnosticStatus: string
{
    use HasValues;

    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
}
