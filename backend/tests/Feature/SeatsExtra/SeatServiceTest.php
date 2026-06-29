<?php

declare(strict_types=1);

namespace Tests\Feature\SeatsExtra;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Direct unit-style coverage of the SeatService create/update/seatMap/delete
 * lifecycle methods (assignment lives in the existing SeatAssignmentTest).
 */
class SeatServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SeatService
    {
        return app(SeatService::class);
    }

    public function test_create_persists_a_seat_with_defaults(): void
    {
        $workspace = Workspace::factory()->create(['total_seats' => 5]);

        $seat = $this->service()->create($workspace, [
            'seat_number' => 'A-01',
            'type' => SeatType::Fixed->value,
        ]);

        $this->assertSame('A-01', $seat->seat_number);
        $this->assertSame(SeatType::Fixed, $seat->type);
        $this->assertSame(SeatStatus::Available, $seat->status);
        $this->assertNull($seat->notes);
        $this->assertSame($workspace->id, $seat->workspace_id);
    }

    public function test_create_honours_explicit_status_and_notes(): void
    {
        $workspace = Workspace::factory()->create(['total_seats' => 5]);

        $seat = $this->service()->create($workspace, [
            'seat_number' => 'A-02',
            'type' => SeatType::Flexible->value,
            'status' => SeatStatus::Maintenance->value,
            'notes' => 'broken leg',
        ]);

        $this->assertSame(SeatStatus::Maintenance, $seat->status);
        $this->assertSame('broken leg', $seat->notes);
    }

    public function test_create_rejects_duplicate_seat_number(): void
    {
        $workspace = Workspace::factory()->create(['total_seats' => 5]);
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_number' => 'DUP',
        ]);

        $this->expectException(HttpException::class);
        $this->service()->create($workspace, [
            'seat_number' => 'DUP',
            'type' => SeatType::Fixed->value,
        ]);
    }

    public function test_create_rejects_when_capacity_is_reached(): void
    {
        $workspace = Workspace::factory()->create(['total_seats' => 1]);
        Seat::factory()->create(['workspace_id' => $workspace->id]);

        $this->expectException(HttpException::class);
        $this->service()->create($workspace, [
            'seat_number' => 'OVER',
            'type' => SeatType::Fixed->value,
        ]);
    }

    public function test_update_renames_seat_number(): void
    {
        $seat = Seat::factory()->create(['seat_number' => 'OLD']);

        $updated = $this->service()->update($seat, ['seat_number' => 'NEW']);

        $this->assertSame('NEW', $updated->seat_number);
    }

    public function test_update_only_touches_whitelisted_attributes(): void
    {
        $workspace = Workspace::factory()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
        ]);

        $other = Workspace::factory()->create();

        $updated = $this->service()->update($seat, [
            'type' => SeatType::Flexible->value,
            'status' => SeatStatus::Maintenance->value,
            'notes' => 'note',
            // These must be ignored by the intersect_key whitelist.
            'workspace_id' => $other->id,
            'assigned_member_id' => null,
        ]);

        $this->assertSame(SeatType::Flexible, $updated->type);
        $this->assertSame(SeatStatus::Maintenance, $updated->status);
        $this->assertSame('note', $updated->notes);
        $this->assertSame($workspace->id, $updated->workspace_id);
    }

    public function test_seat_map_returns_ordered_seats_and_summary(): void
    {
        $workspace = Workspace::factory()->create();
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_number' => 'B-02',
            'status' => SeatStatus::Available->value,
        ]);
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_number' => 'A-01',
            'status' => SeatStatus::Maintenance->value,
        ]);
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_number' => 'C-03',
            'status' => SeatStatus::Occupied->value,
        ]);

        $map = $this->service()->seatMap($workspace);

        $this->assertSame(['A-01', 'B-02', 'C-03'], $map['seats']->pluck('seat_number')->all());
        $this->assertSame([
            'total' => 3,
            'available' => 1,
            'occupied' => 1,
            'maintenance' => 1,
        ], $map['summary']);
    }

    public function test_seat_map_resolves_occupant_from_active_subscription_when_relation_missing(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();

        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Occupied->value,
            // No assigned_member_id — simulating a legacy occupied seat.
            'assigned_member_id' => null,
        ]);

        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'seat_id' => $seat->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $map = $this->service()->seatMap($workspace);

        $resolved = $map['seats']->firstWhere('id', $seat->id);
        $this->assertNotNull($resolved->assignedMember);
        $this->assertSame($member->id, $resolved->assignedMember->id);
    }

    public function test_delete_removes_an_available_unreferenced_seat(): void
    {
        $seat = Seat::factory()->create(['status' => SeatStatus::Available->value]);

        $this->service()->delete($seat);

        $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
    }

    public function test_delete_is_blocked_for_a_non_available_seat(): void
    {
        $seat = Seat::factory()->create(['status' => SeatStatus::Occupied->value]);

        $this->expectException(HttpException::class);
        $this->service()->delete($seat);

        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
    }

    public function test_delete_is_blocked_when_a_subscription_references_the_seat(): void
    {
        $workspace = Workspace::factory()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Available->value,
        ]);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_id' => $seat->id,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->delete($seat);
    }
}
