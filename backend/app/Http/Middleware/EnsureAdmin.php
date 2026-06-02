<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;

final class EnsureAdmin extends EnsureRole
{
    protected function role(): UserRole
    {
        return UserRole::Admin;
    }
}
