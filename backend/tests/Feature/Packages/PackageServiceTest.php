<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Models\InternetPackage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PackageService
    {
        return app(PackageService::class);
    }

    public function test_create_persists_a_package_owned_by_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $package = $this->service()->create($workspace, [
            'name' => 'Pro Fiber',
            'speed_mbps' => 100,
            'price' => 15,
            'data_limit_gb' => 200,
            'is_unlimited' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('internet_packages', [
            'id' => $package->id,
            'workspace_id' => $workspace->id,
            'name' => 'Pro Fiber',
            'speed_mbps' => 100,
        ]);
        $this->assertSame($workspace->id, $package->workspace_id);
        $this->assertSame(200, $package->data_limit_gb);
    }

    public function test_create_nulls_the_data_limit_for_unlimited_packages(): void
    {
        $workspace = Workspace::factory()->create();

        $package = $this->service()->create($workspace, [
            'name' => 'Unlimited',
            'speed_mbps' => 200,
            'price' => 20,
            'data_limit_gb' => 500,
            'is_unlimited' => true,
        ]);

        $this->assertTrue($package->is_unlimited);
        $this->assertNull($package->data_limit_gb);
        $this->assertDatabaseHas('internet_packages', [
            'id' => $package->id,
            'data_limit_gb' => null,
        ]);
    }

    public function test_update_changes_attributes_and_returns_a_fresh_model(): void
    {
        $package = InternetPackage::factory()->create([
            'name' => 'Old',
            'price' => 5,
            'is_unlimited' => false,
            'data_limit_gb' => 50,
        ]);

        $updated = $this->service()->update($package, [
            'name' => 'New',
            'price' => 12,
        ]);

        $this->assertSame('New', $updated->name);
        $this->assertSame('12.00', (string) $updated->price);
        $this->assertDatabaseHas('internet_packages', [
            'id' => $package->id,
            'name' => 'New',
        ]);
    }

    public function test_update_to_unlimited_clears_the_data_limit(): void
    {
        $package = InternetPackage::factory()->create([
            'is_unlimited' => false,
            'data_limit_gb' => 100,
        ]);

        $updated = $this->service()->update($package, ['is_unlimited' => true]);

        $this->assertTrue($updated->is_unlimited);
        $this->assertNull($updated->data_limit_gb);
    }

    public function test_delete_removes_the_package(): void
    {
        $package = InternetPackage::factory()->create();

        $this->service()->delete($package);

        $this->assertDatabaseMissing('internet_packages', ['id' => $package->id]);
    }

    public function test_assign_member_creates_a_single_pivot_row(): void
    {
        $package = InternetPackage::factory()->create();
        $member = User::factory()->freelancer()->create();

        $result = $this->service()->assignMember($package, $member->id);

        $this->assertDatabaseHas('member_package', [
            'package_id' => $package->id,
            'member_id' => $member->id,
        ]);
        $this->assertTrue($result->members->contains('id', $member->id));
        $this->assertDatabaseCount('member_package', 1);
    }

    public function test_assign_member_is_idempotent_and_refreshes_the_timestamp(): void
    {
        $package = InternetPackage::factory()->create();
        $member = User::factory()->freelancer()->create();

        $this->service()->assignMember($package, $member->id);
        $firstAssignedAt = $package->members()
            ->where('member_id', $member->id)
            ->first()->pivot->assigned_at;

        $this->travel(2)->minutes();
        $this->service()->assignMember($package, $member->id);

        $this->assertDatabaseCount('member_package', 1);

        $secondAssignedAt = $package->members()
            ->where('member_id', $member->id)
            ->first()->pivot->assigned_at;
        $this->assertNotSame((string) $firstAssignedAt, (string) $secondAssignedAt);
    }

    public function test_unassign_member_removes_the_pivot_row(): void
    {
        $package = InternetPackage::factory()->create();
        $member = User::factory()->freelancer()->create();
        $this->service()->assignMember($package, $member->id);

        $result = $this->service()->unassignMember($package, $member->id);

        $this->assertDatabaseMissing('member_package', [
            'package_id' => $package->id,
            'member_id' => $member->id,
        ]);
        $this->assertFalse($result->members->contains('id', $member->id));
    }

    public function test_unassign_member_only_affects_the_given_member(): void
    {
        $package = InternetPackage::factory()->create();
        $keep = User::factory()->freelancer()->create();
        $remove = User::factory()->freelancer()->create();
        $this->service()->assignMember($package, $keep->id);
        $this->service()->assignMember($package, $remove->id);

        $this->service()->unassignMember($package, $remove->id);

        $this->assertDatabaseHas('member_package', [
            'package_id' => $package->id,
            'member_id' => $keep->id,
        ]);
        $this->assertDatabaseMissing('member_package', [
            'package_id' => $package->id,
            'member_id' => $remove->id,
        ]);
    }

    public function test_list_for_workspace_returns_only_that_workspaces_packages_with_counts(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $mine = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        InternetPackage::factory()->create(['workspace_id' => $other->id]);
        $member = User::factory()->freelancer()->create();
        $this->service()->assignMember($mine, $member->id);

        $packages = $this->service()->listForWorkspace($workspace);

        $this->assertCount(1, $packages);
        $this->assertSame($mine->id, $packages->first()->id);
        $this->assertSame(1, $packages->first()->members_count);
    }
}
