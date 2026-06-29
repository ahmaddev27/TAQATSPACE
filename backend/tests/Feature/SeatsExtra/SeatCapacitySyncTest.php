<?php

declare(strict_types=1);

namespace Tests\Feature\SeatsExtra;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Models\Seat;
use App\Models\SeatTypePrice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\SeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for setupFromCounts + syncSeatsToCapacity: seat types drive the
 * physical Seat rows, surplus is pruned only when safely removable, and the
 * operation is idempotent.
 */
class SeatCapacitySyncTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SeatService
    {
        return app(SeatService::class);
    }

    public function test_setup_from_counts_creates_typed_seats_with_prefixed_numbers(): void
    {
        $workspace = Workspace::factory()->create();

        $seats = $this->service()->setupFromCounts($workspace, [
            ['type' => SeatType::Flexible->value, 'count' => 2],
            ['type' => SeatType::Fixed->value, 'count' => 1],
            ['type' => SeatType::PrivateOffice->value, 'count' => 0],
        ]);

        $this->assertCount(3, $seats);
        $this->assertSame(['F-01', 'F-02', 'X-01'], $seats->pluck('seat_number')->sort()->values()->all());

        $this->assertSame(2, $workspace->seats()->where('type', SeatType::Flexible->value)->count());
        $this->assertSame(1, $workspace->seats()->where('type', SeatType::Fixed->value)->count());
        $this->assertSame(0, $workspace->seats()->where('type', SeatType::PrivateOffice->value)->count());

        // Seat-type rows reflect the chosen capacities and enabled flags.
        $flex = $workspace->seatTypes()->where('type', SeatType::Flexible->value)->first();
        $this->assertTrue($flex->enabled);
        $this->assertSame(2, (int) $flex->capacity);

        $office = $workspace->seatTypes()->where('type', SeatType::PrivateOffice->value)->first();
        $this->assertFalse($office->enabled);
    }

    public function test_setup_from_counts_is_idempotent(): void
    {
        $workspace = Workspace::factory()->create();
        $counts = [['type' => SeatType::Flexible->value, 'count' => 3]];

        $this->service()->setupFromCounts($workspace, $counts);
        $first = $workspace->seats()->orderBy('seat_number')->pluck('seat_number')->all();

        $this->service()->setupFromCounts($workspace, $counts);
        $second = $workspace->seats()->orderBy('seat_number')->pluck('seat_number')->all();

        $this->assertSame($first, $second);
        $this->assertSame(3, $workspace->seats()->count());
    }

    public function test_sync_creates_missing_seats_for_enabled_type(): void
    {
        $workspace = Workspace::factory()->create();
        SeatTypePrice::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
            'enabled' => true,
            'capacity' => 4,
        ]);

        $this->service()->syncSeatsToCapacity($workspace);

        $this->assertSame(4, $workspace->seats()->where('type', SeatType::Fixed->value)->count());
    }

    public function test_sync_prunes_only_available_surplus_seats(): void
    {
        $workspace = Workspace::factory()->create();
        SeatTypePrice::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
            'enabled' => true,
            'capacity' => 1,
        ]);

        // Two existing fixed seats: one available (removable), one occupied (kept).
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
            'seat_number' => 'X-09',
            'status' => SeatStatus::Available->value,
        ]);
        Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
            'seat_number' => 'X-01',
            'status' => SeatStatus::Occupied->value,
        ]);

        $this->service()->syncSeatsToCapacity($workspace);

        // The occupied seat survives even though it pushes count above capacity.
        $this->assertDatabaseMissing('seats', ['workspace_id' => $workspace->id, 'seat_number' => 'X-09']);
        $this->assertDatabaseHas('seats', ['workspace_id' => $workspace->id, 'seat_number' => 'X-01']);
    }

    public function test_sync_keeps_surplus_seat_referenced_by_a_subscription(): void
    {
        $workspace = Workspace::factory()->create();
        SeatTypePrice::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
            'enabled' => true,
            'capacity' => 0,
        ]);

        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed->value,
            'status' => SeatStatus::Available->value,
        ]);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_id' => $seat->id,
        ]);

        $this->service()->syncSeatsToCapacity($workspace);

        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
    }

    public function test_sync_removes_available_seats_of_a_disabled_type(): void
    {
        $workspace = Workspace::factory()->create();
        SeatTypePrice::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Flexible->value,
            'enabled' => false,
            'capacity' => 5,
        ]);
        Seat::factory()->count(3)->sequence(
            ['seat_number' => 'F-01'],
            ['seat_number' => 'F-02'],
            ['seat_number' => 'F-03'],
        )->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Flexible->value,
            'status' => SeatStatus::Available->value,
        ]);

        $this->service()->syncSeatsToCapacity($workspace);

        $this->assertSame(0, $workspace->seats()->where('type', SeatType::Flexible->value)->count());
    }
}
