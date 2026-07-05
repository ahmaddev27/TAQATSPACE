<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case Freelancer = 'freelancer';
    case WorkspaceOwner = 'workspace_owner';
    case Admin = 'admin';
    case Cashier = 'cashier';

    /**
     * Roles that may self-register through the public API.
     *
     * @return array<int, string>
     */
    public static function registerable(): array
    {
        return [self::Freelancer->value, self::WorkspaceOwner->value];
    }
}
