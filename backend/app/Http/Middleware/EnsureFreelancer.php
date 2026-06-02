<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;

final class EnsureFreelancer extends EnsureRole
{
    protected function role(): UserRole
    {
        return UserRole::Freelancer;
    }
}
