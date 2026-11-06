<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriberSegment: string
{
    case Champion = 'champion';
    case Regular = 'regular';
    case AtRisk = 'at_risk';
    case AboutToChurn = 'about_to_churn';
    case New = 'new';
    case Dormant = 'dormant';
}
