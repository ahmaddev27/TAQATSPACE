<?php

declare(strict_types=1);

namespace Tests\Feature\Seats;

use App\Models\Branch;
use App\Models\Seat;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function owner(): array
    {
        $owner = User::factory()->owner()->create();

        return [$owner, Workspace::factory()->create(['owner_id' => $owner->id, 'total_seats' => 10])];
    }

    public function test_owner_lists_creates_and_updates_branches(): void
    {
        [$owner, $workspace] = $this->owner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/branches')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_default', true);

        $id = $this->postJson('/api/workspace/branches', ['name' => 'Annex', 'address' => 'Street 2'])
            ->assertCreated()->assertJsonPath('data.is_default', false)->json('data.id');

        $this->putJson("/api/workspace/branches/{$id}", ['name' => 'Annex 2'])
            ->assertOk()->assertJsonPath('data.name', 'Annex 2');

        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.created', 'workspace_id' => $workspace->id]);
    }

    public function test_default_branch_and_branches_with_seats_cannot_be_deleted(): void
    {
        [$owner, $workspace] = $this->owner();
        $default = Branch::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->where('is_default', true)->firstOrFail();
        $annex = Branch::factory()->create(['workspace_id' => $workspace->id]);
        $empty = Branch::factory()->create(['workspace_id' => $workspace->id]);
        Seat::factory()->create(['workspace_id' => $workspace->id, 'branch_id' => $annex->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/branches/{$default->id}")->assertStatus(409);
        $this->deleteJson("/api/workspace/branches/{$annex->id}")->assertStatus(409);
        $this->deleteJson("/api/workspace/branches/{$empty->id}")->assertOk();
        $this->assertDatabaseMissing('branches', ['id' => $empty->id]);
    }

    public function test_another_workspace_branch_is_not_reachable(): void
    {
        [$owner] = $this->owner();
        $foreign = Branch::factory()->create();
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/branches/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/workspace/branches/{$foreign->id}")->assertNotFound();
    }

    public function test_validation_and_role_guard(): void
    {
        [$owner] = $this->owner();
        Sanctum::actingAs($owner);
        $this->postJson('/api/workspace/branches', [])->assertStatus(422)->assertJsonValidationErrors('name');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/workspace/branches')->assertForbidden();
    }
}
