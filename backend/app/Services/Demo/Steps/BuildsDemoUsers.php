<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoMarker;

trait BuildsDemoUsers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(DemoContext $context, string $key, string $name, UserRole $role, array $attributes = []): User
    {
        // No phone: a demo account must never be reachable by SMS.
        $user = User::query()->create([
            'name' => $name,
            'email' => DemoMarker::emailFor(str_replace('_', '.', $key)),
            'password' => $context->passwordHash,
            'role' => $role,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
            ...$attributes,
        ]);

        $context->addUser($key, $user);

        return $user;
    }
}
