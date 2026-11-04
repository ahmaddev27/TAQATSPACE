<?php

declare(strict_types=1);

namespace Tests\Feature\Seats;

use App\Enums\SeatType;
use App\Models\Branch;
use App\Models\Seat;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeatBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function owner(int $totalSeats = 300): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'total_seats' => $totalSeats]);

        return [$owner, $workspace];
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + ['start' => 1, 'count' => 80, 'prefix' => 'D-', 'type' => SeatType::Fixed->value];
    }

    public function test_adds_eighty_desks_in_one_operation_with_a_constant_query_count(): void
    {
        [$owner, $workspace] = $this->owner();
        Sanctum::actingAs($owner);

        // The first request pays one-off auth/session queries, so warm up before measuring.
        $this->postJson('/api/workspace/seats/bulk', $this->payload(['start' => 5000, 'count' => 1]))->assertCreated();

        DB::enableQueryLog();
        $this->postJson('/api/workspace/seats/bulk', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.created', 80);
        $eighty = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->postJson('/api/workspace/seats/bulk', $this->payload(['start' => 1000, 'count' => 5]))->assertCreated();
        $five = count(DB::getQueryLog());

        $this->assertSame(1 + 80 + 5, $workspace->seats()->count());
        $this->assertSame($five, $eighty);
        $this->assertDatabaseHas('seats', ['workspace_id' => $workspace->id, 'seat_number' => 'D-80']);
    }

    public function test_new_stations_land_in_the_default_branch_with_the_given_attributes(): void
    {
        [$owner, $workspace] = $this->owner();
        Sanctum::actingAs($owner);
        $default = Branch::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->where('is_default', true)->firstOrFail();

        $this->postJson('/api/workspace/seats/bulk', $this->payload([
            'count' => 3, 'zone' => 'North', 'kind' => 'booth', 'is_private' => true, 'capacity' => 2, 'pad' => 2,
        ]))->assertCreated();

        $seat = Seat::query()->where('seat_number', 'D-01')->firstOrFail();
        $this->assertSame($default->id, $seat->branch_id);
        $this->assertSame('North', $seat->zone);
        $this->assertSame('booth', $seat->kind->value);
        $this->assertTrue($seat->is_private);
        $this->assertSame(2, $seat->capacity);
    }

    public function test_an_existing_number_rejects_the_whole_operation(): void
    {
        [$owner, $workspace] = $this->owner();
        Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'D-5']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/seats/bulk', $this->payload(['count' => 10]))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => __('messages.seat_bulk_numbers_exist', ['numbers' => 'D-5'])]);

        $this->assertSame(1, $workspace->seats()->count());
    }

    public function test_rejects_more_than_two_hundred_and_beyond_workspace_capacity(): void
    {
        [$owner] = $this->owner(10);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/seats/bulk', $this->payload(['count' => 201]))
            ->assertStatus(422)->assertJsonValidationErrors('count');
        $this->postJson('/api/workspace/seats/bulk', $this->payload(['count' => 11]))->assertStatus(422);
    }

    public function test_branch_must_belong_to_the_owner_workspace(): void
    {
        [$owner] = $this->owner();
        $foreign = Branch::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/seats/bulk', $this->payload(['branch_id' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_non_owner_cannot_bulk_create(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/workspace/seats/bulk', $this->payload())->assertForbidden();
    }

    public function test_seat_map_filters_by_branch_and_zone(): void
    {
        [$owner, $workspace] = $this->owner();
        $annex = Branch::factory()->create(['workspace_id' => $workspace->id]);
        Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'A1', 'branch_id' => $annex->id, 'zone' => 'Z1']);
        Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'A2', 'branch_id' => $annex->id, 'zone' => 'Z2']);
        Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'B1']);
        Sanctum::actingAs($owner);

        $this->getJson("/api/workspace/seats?branch_id={$annex->id}")->assertJsonCount(2, 'data.seats');
        $this->getJson("/api/workspace/seats?branch_id={$annex->id}&zone=Z2")
            ->assertJsonCount(1, 'data.seats')->assertJsonPath('data.seats.0.seat_number', 'A2');
        $this->getJson("/api/workspaces/{$workspace->id}/seats?zone=Z1")->assertJsonCount(1, 'data.seats');
    }

    public function test_station_fields_can_be_updated_with_a_parent_in_the_same_workspace(): void
    {
        [$owner, $workspace] = $this->owner();
        $room = Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'R1']);
        $desk = Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'R1-1']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/seats/{$desk->id}", ['parent_id' => $room->id, 'zone' => 'Rooms', 'is_private' => true])
            ->assertOk()->assertJsonPath('data.parent_id', $room->id);
        $this->putJson("/api/seats/{$desk->id}", ['parent_id' => $desk->id])->assertStatus(422);
    }

    public function test_month_occupancy_counts_distinct_days_from_subscriptions(): void
    {
        [$owner, $workspace] = $this->owner();
        $seat = Seat::factory()->create(['workspace_id' => $workspace->id, 'seat_number' => 'A1']);
        \App\Models\Subscription::factory()->create([
            'workspace_id' => $workspace->id, 'seat_id' => $seat->id, 'status' => 'active',
            'start_date' => '2026-03-21', 'end_date' => '2026-04-10',
        ]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/seats/occupancy?month=2026-03')
            ->assertOk()
            ->assertJsonPath('data.seats.0.occupied_days', 11)
            ->assertJsonPath('data.days_in_month', 31);
    }
}
