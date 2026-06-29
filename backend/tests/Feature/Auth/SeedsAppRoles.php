<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ensures the Spatie roles the registration/onboarding flows assign exist for
 * the duration of a test. RefreshDatabase wipes them between tests, so each test
 * that triggers role assignment must seed them first.
 */
trait SeedsAppRoles
{
    protected function seedAppRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
