<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Current work situation of a subscriber, collected for donor demographics. */
enum EmploymentStatus: string
{
    use HasValues;

    case Employed = 'employed';
    case SelfEmployed = 'self_employed';
    case Freelancer = 'freelancer';
    case Unemployed = 'unemployed';
    case Student = 'student';
    case Retired = 'retired';
    case Other = 'other';
}
