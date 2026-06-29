<?php

declare(strict_types=1);

namespace Tests\Feature\SeatsExtra;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HTTP coverage for the owner-only seat mutation endpoints
 * (store / update / destroy) including auth and ownership guards.
 */
class SeatControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function owner(int $totalSeats = 10): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'total_seats' => $totalSeats,
        ]);

        return [$owner, $workspace];
    }

    public function test_store_creates_a_seat_for_the_authenticated_owner(): void
    {
        [$owner] = $this->owner();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/workspace/seats', [
            'seat_number' => 'A-10',
            'type' => SeatType::Fixed->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.seat_number', 'A-10')
            ->assertJsonPath('data.type', SeatType::Fixed->value)
            ->assertJsonPath('data.status', SeatStatus::Available->value);

        $this->assertDatabaseHas('seats', [
            'seat_number' => 'A-10',
            'workspace_id' => $owner->workspace->id,
        ]);
    }

    public function test_store_rejects_unauthenticated_request(): void
    {
        $this->postJson('/api/workspace/seats', [
            'seat_number' => 'A-10',
            'type' => SeatType::Fixed->value,
        ])->assertUnauthorized();
    }

    public function test_store_is_forbidden_for_a_non_owner_role(): void
    {
        $member = User::factory()->freelancer()->create();
        Sanctum::actingAs($member);

        $this->postJson('/api/workspace/seats', [
            'seat_number' => 'A-10',
            'type' => SeatType::Fixed->value,
        ])->assertForbidden();
    }

    public function test_store_validates_the_payload(): void
    {
        [$owner] = $this->owner();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/seats', [
            'type' => 'not-a-real-type',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['seat_number', 'type']);
    }

    public function test_store_rejects_a_duplicate_seat_number(): void
    {
        [$owner, $workspace] = $this->owner();
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_number' => 'DUP',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/seats', [
            'seat_number' => 'DUP',
            'type' => SeatType::Fixed->value,
        ])->assertStatus(422);
    }

    public function test_update_renames_a_seat(): void
    {
        [$owner, $workspace] = $this->owner();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_number' => 'OLD',
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/seats/{$seat->id}", ['seat_number' => 'NEW'])
            ->assertOk()
            ->assertJsonPath('data.seat_number', 'NEW');

        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'seat_number' => 'NEW']);
    }

    public function test_update_rejects_a_seat_number_already_used_in_the_workspace(): void
    {
        [$owner, $workspace] = $this->owner();
        Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'TAKEN']);
        $seat = Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'MINE']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/seats/{$seat->id}", ['seat_number' => 'TAKEN'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['seat_number']);
    }

    public function test_update_allows_keeping_the_same_seat_number(): void
    {
        [$owner, $workspace] = $this->owner();
        $seat = Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'SAME']);
        Sanctum::actingAs($owner);

        // Same value ignores itself in the unique rule; only status changes.
        $this->putJson("/api/seats/{$seat->id}", [
            'seat_number' => 'SAME',
            'status' => SeatStatus::Maintenance->value,
        ])->assertOk()
            ->assertJsonPath('data.status', SeatStatus::Maintenance->value);
    }

    public function test_update_is_forbidden_for_another_owners_seat(): void
    {
        $intruder = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $intruder->id]);

        $otherWorkspace = Workspace::factory()->create();
        $seat = Seat::factory()->create(['workspace_id' => $otherWorkspace->id]);

        Sanctum::actingAs($intruder);

        $this->putJson("/api/seats/{$seat->id}", ['status' => SeatStatus::Maintenance->value])
            ->assertForbidden();
    }

    public function test_destroy_deletes_an_available_seat(): void
    {
        [$owner, $workspace] = $this->owner();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Available->value,
        ]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/seats/{$seat->id}")->assertOk();

        $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
    }

    public function test_destroy_is_blocked_for_an_occupied_seat(): void
    {
        [$owner, $workspace] = $this->owner();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Occupied->value,
        ]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/seats/{$seat->id}")->assertStatus(409);
        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
    }

    public function test_destroy_is_blocked_when_a_subscription_references_the_seat(): void
    {
        [$owner, $workspace] = $this->owner();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Available->value,
        ]);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_id' => $seat->id,
        ]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/seats/{$seat->id}")->assertStatus(409);
        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
    }

    public function test_destroy_is_forbidden_for_another_owners_seat(): void
    {
        $intruder = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $intruder->id]);

        $otherWorkspace = Workspace::factory()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'status' => SeatStatus::Available->value,
        ]);

        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/seats/{$seat->id}")->assertForbidden();
    }
}
