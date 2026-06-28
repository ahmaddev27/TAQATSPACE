<?php

declare(strict_types=1);

namespace Tests\Feature\Seats;

use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeatAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SeatService
    {
        return app(SeatService::class);
    }

    /** @return array{0: Workspace, 1: User, 2: Subscription, 3: Seat} */
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

        return [$workspace, $member, $subscription, $seat];
    }

    public function test_assign_marks_the_seat_and_syncs_the_subscription(): void
    {
        Notification::fake();
        [, $member, $subscription, $seat] = $this->scenario();

        $this->service()->assign($seat, $member);

        $seat->refresh();
        $this->assertSame(SeatStatus::Occupied, $seat->status);
        $this->assertSame($member->id, $seat->assigned_member_id);
        $this->assertSame($seat->id, $subscription->refresh()->seat_id);
    }

    public function test_unassign_frees_the_seat_and_clears_the_subscription(): void
    {
        Notification::fake();
        [, $member, $subscription, $seat] = $this->scenario();
        $this->service()->assign($seat, $member);

        $this->service()->unassign($seat->refresh());

        $seat->refresh();
        $this->assertSame(SeatStatus::Available, $seat->status);
        $this->assertNull($seat->assigned_member_id);
        $this->assertNull($subscription->refresh()->seat_id);
    }

    public function test_assign_requires_an_active_subscription(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Available->value,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->assign($seat, $member);
    }

    public function test_a_member_cannot_hold_two_seats(): void
    {
        Notification::fake();
        [$workspace, $member, , $seat] = $this->scenario();
        $this->service()->assign($seat, $member);

        $second = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Available->value,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->assign($second, $member);
    }
}
