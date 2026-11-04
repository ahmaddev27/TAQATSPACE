<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Highest completed education level, as the donor reports require it.
 * Labels live in the frontend message files; only the stable codes are stored.
 */
enum EducationLevel: string
{
    use HasValues;

    case NoFormal = 'no_formal';
    case Primary = 'primary';
    case LowerSecondary = 'lower_secondary';
    case Secondary = 'secondary';
    case Vocational = 'vocational';
    case IntermediateDiploma = 'intermediate_diploma';
    case Bachelor = 'bachelor';
    case HigherDiploma = 'higher_diploma';
    case Master = 'master';
    case Doctorate = 'doctorate';
}
