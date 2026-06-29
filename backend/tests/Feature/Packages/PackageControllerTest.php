<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Enums\SubscriptionStatus;
use App\Models\InternetPackage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Workspace, 1: User} */
    private function ownerWithWorkspace(): array
    {
        $workspace = Workspace::factory()->create();

        return [$workspace, $workspace->owner];
    }

    private function memberOf(Workspace $workspace): User
    {
        $member = User::factory()->freelancer()->create();
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active->value,
            'seat_id' => null,
        ]);

        return $member;
    }

    public function test_index_returns_only_the_owners_packages(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $mine = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        InternetPackage::factory()->create(); // other workspace

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/workspace/packages');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/workspace/packages')->assertUnauthorized();
    }

    public function test_non_owners_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->getJson('/api/workspace/packages')->assertForbidden();
    }

    public function test_store_creates_a_package_for_the_owner_workspace(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/workspace/packages', [
            'name' => 'Fiber Pro',
            'speed_mbps' => 100,
            'price' => 15,
            'data_limit_gb' => 200,
            'is_unlimited' => false,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Fiber Pro')
            ->assertJsonPath('data.workspace_id', $workspace->id);

        $this->assertDatabaseHas('internet_packages', [
            'workspace_id' => $workspace->id,
            'name' => 'Fiber Pro',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        [, $owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/packages', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'speed_mbps', 'price', 'is_unlimited']);
    }

    public function test_store_requires_a_data_limit_when_not_unlimited(): void
    {
        [, $owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/packages', [
            'name' => 'Capped',
            'speed_mbps' => 50,
            'price' => 10,
            'is_unlimited' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['data_limit_gb']);
    }

    public function test_update_modifies_an_owned_package(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $package = InternetPackage::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Old',
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/packages/{$package->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertDatabaseHas('internet_packages', [
            'id' => $package->id,
            'name' => 'Renamed',
        ]);
    }

    public function test_update_on_a_foreign_package_returns_not_found(): void
    {
        [, $owner] = $this->ownerWithWorkspace();
        $foreign = InternetPackage::factory()->create();
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/packages/{$foreign->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }

    public function test_destroy_deletes_an_owned_package(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $package = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/packages/{$package->id}")->assertOk();

        $this->assertDatabaseMissing('internet_packages', ['id' => $package->id]);
    }

    public function test_destroy_on_a_foreign_package_returns_not_found(): void
    {
        [, $owner] = $this->ownerWithWorkspace();
        $foreign = InternetPackage::factory()->create();
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/packages/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('internet_packages', ['id' => $foreign->id]);
    }

    public function test_assign_attaches_a_workspace_member(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $package = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        $member = $this->memberOf($workspace);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/packages/{$package->id}/assign", [
            'member_id' => $member->id,
        ])->assertOk();

        $this->assertDatabaseHas('member_package', [
            'package_id' => $package->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_assign_rejects_a_member_outside_the_workspace(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $package = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        $outsider = User::factory()->freelancer()->create();
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/packages/{$package->id}/assign", [
            'member_id' => $outsider->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('member_package', [
            'package_id' => $package->id,
            'member_id' => $outsider->id,
        ]);
    }

    public function test_assign_validates_a_known_member_id(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $package = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/packages/{$package->id}/assign", [
            'member_id' => 'non-existent-id',
        ])->assertStatus(422)->assertJsonValidationErrors(['member_id']);
    }

    public function test_unassign_detaches_a_member(): void
    {
        [$workspace, $owner] = $this->ownerWithWorkspace();
        $package = InternetPackage::factory()->create(['workspace_id' => $workspace->id]);
        $member = $this->memberOf($workspace);
        Sanctum::actingAs($owner);
        $this->putJson("/api/workspace/packages/{$package->id}/assign", [
            'member_id' => $member->id,
        ])->assertOk();

        $this->putJson("/api/workspace/packages/{$package->id}/unassign", [
            'member_id' => $member->id,
        ])->assertOk();

        $this->assertDatabaseMissing('member_package', [
            'package_id' => $package->id,
            'member_id' => $member->id,
        ]);
    }
}
