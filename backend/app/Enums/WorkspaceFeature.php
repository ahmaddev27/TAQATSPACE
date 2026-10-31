<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Modules that are rolled out per workspace. Every flag is off until a super
 * admin enables it, so unfinished modules never reach existing workspaces.
 */
enum WorkspaceFeature: string
{
    use HasValues;

    case Network = 'network';
    case FinanceV2 = 'finance_v2';
    case Gsg = 'gsg';
}
