<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\Seat;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Bus::fake();
        Queue::fake();
        Storage::fake('public');
    }

    public function test_index_returns_paginated_requests_for_the_owner_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        BookingRequest::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        // Noise from another workspace must not leak in.
        BookingRequest::factory()->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/workspace/booking-requests');

        $response->assertOk()
            ->assertJsonCount(3, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'workspace_id', 'status', 'plan_type', 'member' => ['id', 'name']]],
                    'meta' => ['current_page', 'total'],
                ],
            ]);
    }

    public function test_index_filters_by_status(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        BookingRequest::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'status' => BookingStatus::Pending,
        ]);
        BookingRequest::factory()->approved($owner)->create(['workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/workspace/booking-requests?status='.BookingStatus::Pending->value);

        $response->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_index_requires_owner_role(): void
    {
        $freelancer = User::factory()->freelancer()->create();
        Sanctum::actingAs($freelancer);

        $this->getJson('/api/workspace/booking-requests')->assertForbidden();
    }

    public function test_update_approve_assigns_seat_and_returns_approved_request(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->freelancer()->create();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => SeatType::Fixed,
            'status' => SeatStatus::Available,
        ]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'preferred_seat_type' => SeatType::Fixed,
            'status' => BookingStatus::Pending,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/workspace/booking-requests/{$booking->id}", [
            'action' => 'approve',
            'seat_id' => $seat->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Approved->value);

        $this->assertSame(SeatStatus::Occupied, $seat->refresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'member_id' => $member->id,
            'seat_id' => $seat->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
        Notification::assertSentTo($member, BookingApprovedNotification::class);
    }

    public function test_update_reject_records_reason_and_returns_rejected_request(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->freelancer()->create();
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => BookingStatus::Pending,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/workspace/booking-requests/{$booking->id}", [
            'action' => 'reject',
            'rejection_reason' => 'لا يوجد مقاعد متاحة.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'لا يوجد مقاعد متاحة.');

        Notification::assertSentTo($member, BookingRejectedNotification::class);
    }

    public function test_update_forbids_a_non_owner_of_the_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => BookingStatus::Pending,
        ]);

        $intruder = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $intruder->id]);

        Sanctum::actingAs($intruder);

        $this->putJson("/api/workspace/booking-requests/{$booking->id}", [
            'action' => 'reject',
            'rejection_reason' => 'nope',
        ])->assertForbidden();
    }

    public function test_update_validates_the_action(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => BookingStatus::Pending,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/booking-requests/{$booking->id}", [
            'action' => 'maybe',
        ])->assertStatus(422)->assertJsonValidationErrors('action');
    }

    public function test_update_requires_authentication(): void
    {
        $booking = BookingRequest::factory()->create();

        $this->putJson("/api/workspace/booking-requests/{$booking->id}", [
            'action' => 'reject',
        ])->assertUnauthorized();
    }
}
