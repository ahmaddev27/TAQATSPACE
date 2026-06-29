<?php

declare(strict_types=1);

namespace Tests\Feature\ExpensesResources;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $workspace];
    }

    public function test_index_returns_resources_summary_and_meta(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Resource::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/resources')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'resources' => [['id', 'name', 'type', 'quantity', 'status', 'notes']],
                    'summary' => ['total', 'by_status'],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ])
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.summary.total', 2);
    }

    public function test_index_only_returns_the_owners_workspace_resources(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $other = Workspace::factory()->create();
        Resource::factory()->create(['workspace_id' => $workspace->id]);
        Resource::factory()->count(4)->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/resources')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_store_creates_a_resource_and_returns_201(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $payload = [
            'name' => 'Conference Room',
            'type' => ResourceType::MeetingRoom->value,
            'quantity' => 3,
            'status' => ResourceStatus::Available->value,
            'notes' => null,
        ];

        $this->postJson('/api/workspace/resources', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Conference Room')
            ->assertJsonPath('data.type', ResourceType::MeetingRoom->value)
            ->assertJsonPath('data.quantity', 3);

        $this->assertDatabaseHas('workspace_resources', [
            'workspace_id' => $workspace->id,
            'name' => 'Conference Room',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/resources', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'quantity', 'status']);
    }

    public function test_store_rejects_invalid_type_and_status(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/resources', [
            'name' => 'X',
            'type' => 'spaceship',
            'quantity' => 1,
            'status' => 'exploded',
        ])->assertStatus(422)->assertJsonValidationErrors(['type', 'status']);
    }

    public function test_update_modifies_an_owned_resource(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $resource = Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => ResourceStatus::Available->value,
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/resources/{$resource->id}", [
            'name' => 'Renamed',
            'type' => ResourceType::Equipment->value,
            'quantity' => 7,
            'status' => ResourceStatus::Maintenance->value,
        ])->assertOk()->assertJsonPath('data.status', ResourceStatus::Maintenance->value);

        $this->assertDatabaseHas('workspace_resources', [
            'id' => $resource->id,
            'name' => 'Renamed',
            'status' => ResourceStatus::Maintenance->value,
        ]);
    }

    public function test_update_forbidden_for_foreign_resource(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        $other = Workspace::factory()->create();
        $foreign = Resource::factory()->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/resources/{$foreign->id}", [
            'name' => 'Hacked',
            'type' => ResourceType::Other->value,
            'quantity' => 1,
            'status' => ResourceStatus::Available->value,
        ])->assertStatus(403);
    }

    public function test_destroy_deletes_an_owned_resource(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $resource = Resource::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/resources/{$resource->id}")->assertOk();

        $this->assertDatabaseMissing('workspace_resources', ['id' => $resource->id]);
    }

    public function test_destroy_forbidden_for_foreign_resource(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        $other = Workspace::factory()->create();
        $foreign = Resource::factory()->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/resources/{$foreign->id}")->assertStatus(403);
        $this->assertDatabaseHas('workspace_resources', ['id' => $foreign->id]);
    }

    public function test_guest_cannot_access_resources(): void
    {
        $this->getJson('/api/workspace/resources')->assertUnauthorized();
    }

    public function test_non_owner_is_forbidden(): void
    {
        $freelancer = User::factory()->freelancer()->create();
        Sanctum::actingAs($freelancer);

        $this->getJson('/api/workspace/resources')->assertStatus(403);
    }
}
