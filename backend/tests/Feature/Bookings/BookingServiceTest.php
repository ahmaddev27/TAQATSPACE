<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PlanType;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceStatus;
use App\Models\BookingRequest;
use App\Models\Seat;
use App\Models\SeatTypePrice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\NewBookingRequestNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    private function fakeSideEffects(): void
    {
        Notification::fake();
        Bus::fake();
        Queue::fake();
    }

    public function test_submit_creates_a_pending_request_and_notifies_the_owner(): void
    {
        $this->fakeSideEffects();
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->freelancer()->create();

        $booking = $this->service()->submit($member, [
            'workspace_id' => $workspace->id,
            'preferred_seat_type' => SeatType::Fixed->value,
            'plan_type' => PlanType::Monthly->value,
            'message' => 'مرحبا',
        ]);

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame($member->id, $booking->member_id);
        $this->assertSame($workspace->id, $booking->workspace_id);
        $this->assertDatabaseHas('booking_requests', [
            'id' => $booking->id,
            'status' => BookingStatus::Pending->value,
        ]);

        Notification::assertSentTo($owner, NewBookingRequestNotification::class);
    }

    public function test_submit_defaults_plan_to_monthly_when_omitted(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();

        $booking = $this->service()->submit($member, [
            'workspace_id' => $workspace->id,
        ]);

        $this->assertSame(PlanType::Monthly, $booking->plan_type);
    }

    public function test_submit_rejects_an_inactive_workspace(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create(['status' => WorkspaceStatus::Suspended]);
        $member = User::factory()->freelancer()->create();

        $this->expectException(HttpException::class);
        $this->service()->submit($member, ['workspace_id' => $workspace->id]);
    }

    public function test_submit_blocks_a_second_pending_request(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        BookingRequest::factory()->create([
            'member_id' => $member->id,
            'status' => BookingStatus::Pending,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->submit($member, ['workspace_id' => $workspace->id]);
    }

    public function test_submit_blocks_when_already_subscribed_to_the_workspace(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        Subscription::factory()->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->submit($member, ['workspace_id' => $workspace->id]);
    }

    public function test_approve_assigns_seat_activates_subscription_and_notifies_member(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create(['price_per_month' => 40]);
        $member = User::factory()->freelancer()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed,
            'status' => SeatStatus::Available,
            'assigned_member_id' => null,
        ]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => SeatType::Fixed,
            'plan_type' => PlanType::Monthly,
            'status' => BookingStatus::Pending,
        ]);
        $reviewer = $workspace->owner;

        $approved = $this->service()->approve($booking, $reviewer, $seat->id);

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertSame($reviewer->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        $seat->refresh();
        $this->assertSame(SeatStatus::Occupied, $seat->status);
        $this->assertSame($member->id, $seat->assigned_member_id);

        $this->assertDatabaseHas('subscriptions', [
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
            'seat_id' => $seat->id,
            'plan_type' => PlanType::Monthly->value,
            'status' => SubscriptionStatus::Active->value,
        ]);

        Notification::assertSentTo($member, BookingApprovedNotification::class);
    }

    public function test_approve_prices_from_seat_type_price_when_configured(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create(['price_per_month' => 40]);
        $member = User::factory()->freelancer()->create();
        SeatTypePrice::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed,
            'price_monthly' => 123.45,
        ]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => SeatType::Fixed,
            'plan_type' => PlanType::Monthly,
            'status' => BookingStatus::Pending,
        ]);

        $this->service()->approve($booking, $workspace->owner, null);

        $subscription = Subscription::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertSame('123.45', (string) $subscription->monthly_price);
    }

    public function test_approve_without_a_seat_activates_subscription_with_null_seat(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => null,
            'plan_type' => PlanType::Daily,
            'status' => BookingStatus::Pending,
        ]);

        $approved = $this->service()->approve($booking, $workspace->owner, null);

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertDatabaseHas('subscriptions', [
            'member_id' => $member->id,
            'seat_id' => null,
            'plan_type' => PlanType::Daily->value,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_approve_rejects_a_seat_from_another_workspace(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $foreignSeat = Seat::factory()->create(['workspace_id' => $other->id]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => null,
            'status' => BookingStatus::Pending,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->approve($booking, $workspace->owner, $foreignSeat->id);
    }

    public function test_approve_rejects_an_unavailable_seat(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Occupied,
        ]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => null,
            'status' => BookingStatus::Pending,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->approve($booking, $workspace->owner, $seat->id);
    }

    public function test_approve_rejects_a_seat_type_mismatch(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Flexible,
            'status' => SeatStatus::Available,
        ]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => SeatType::Fixed,
            'status' => BookingStatus::Pending,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->approve($booking, $workspace->owner, $seat->id);
    }

    public function test_approve_blocks_an_already_reviewed_request(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $booking = BookingRequest::factory()->approved()->create([
            'workspace_id' => $workspace->id,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->approve($booking, $workspace->owner, null);
    }

    public function test_reject_records_reason_and_notifies_member(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => BookingStatus::Pending,
        ]);

        $rejected = $this->service()->reject($booking, $workspace->owner, 'لا تتوفر مقاعد.');

        $this->assertSame(BookingStatus::Rejected, $rejected->status);
        $this->assertSame('لا تتوفر مقاعد.', $rejected->rejection_reason);
        $this->assertSame($workspace->owner->id, $rejected->reviewed_by);
        $this->assertNotNull($rejected->reviewed_at);

        Notification::assertSentTo($member, BookingRejectedNotification::class);
        $this->assertDatabaseMissing('subscriptions', ['member_id' => $member->id]);
    }

    public function test_reject_blocks_an_already_reviewed_request(): void
    {
        $this->fakeSideEffects();
        $workspace = Workspace::factory()->create();
        $booking = BookingRequest::factory()->rejected()->create([
            'workspace_id' => $workspace->id,
        ]);

        $this->expectException(HttpException::class);
        $this->service()->reject($booking, $workspace->owner, 'reason');
    }
}
