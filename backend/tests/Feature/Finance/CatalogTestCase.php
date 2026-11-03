<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PosPermission;
use App\Enums\UserRole;
use App\Enums\WorkspaceFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CatalogTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    protected function owningWorkspace(bool $financeV2 = true): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        if ($financeV2) {
            WorkspaceFeatureFlag::query()->create([
                'workspace_id' => $workspace->id,
                'feature' => WorkspaceFeature::FinanceV2->value,
                'enabled' => true,
            ]);
        }

        return [$owner, $workspace];
    }

    /**
     * @param  array<int, string>|null  $permissions  defaults to the cashier defaults
     */
    protected function cashierFor(Workspace $workspace, ?array $permissions = null): User
    {
        $cashier = User::factory()->create([
            'role' => UserRole::Cashier->value,
            'workspace_id' => $workspace->id,
        ]);
        $cashier->assignRole(UserRole::Cashier->value);
        $cashier->syncPermissions($permissions ?? PosPermission::defaultsForCashier());

        return $cashier->fresh();
    }
}
