<?php

declare(strict_types=1);

namespace Tests\Feature\Seats;

use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SeatObserver keeps a subscription's seat_id in sync whenever a seat's
 * assigned_member_id changes — even when the change bypasses SeatService.
 */
class SeatObserverTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Subscription, 2: Seat} */
    private function scenario(): array
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active->value,
            'seat_id' => null,
        ]);
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Available->value,
            'assigned_member_id' => null,
        ]);

        return [$member, $subscription, $seat];
    }

    public function test_assigning_a_member_directly_points_their_subscription_at_the_seat(): void
    {
        [$member, $subscription, $seat] = $this->scenario();

        // Bypass SeatService entirely — a raw model update must still sync.
        $seat->update(['assigned_member_id' => $member->id]);

        $this->assertSame($seat->id, $subscription->refresh()->seat_id);
    }

    public function test_clearing_the_seats_member_releases_the_subscription(): void
    {
        [$member, $subscription, $seat] = $this->scenario();
        $seat->update(['assigned_member_id' => $member->id]);

        $seat->update(['assigned_member_id' => null]);

        $this->assertNull($subscription->refresh()->seat_id);
    }

    public function test_reassigning_to_a_new_member_moves_the_seat_reference(): void
    {
        [$first, $firstSub, $seat] = $this->scenario();
        $seat->update(['assigned_member_id' => $first->id]);

        $second = User::factory()->freelancer()->create();
        $secondSub = Subscription::factory()->create([
            'workspace_id' => $seat->workspace_id,
            'member_id' => $second->id,
            'status' => SubscriptionStatus::Active->value,
            'seat_id' => null,
        ]);

        $seat->update(['assigned_member_id' => $second->id]);

        $this->assertNull($firstSub->refresh()->seat_id);
        $this->assertSame($seat->id, $secondSub->refresh()->seat_id);
    }
}
