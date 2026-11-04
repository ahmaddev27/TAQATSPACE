<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\BookingStatus;
use App\Enums\PlanType;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\InternetPackage;
use App\Models\Seat;
use App\Models\Subscription;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;

/** Seats, internet packages, booking requests and a subscription in every status. */
class MembershipStep implements DemoSeedStep
{
    /** @var array<string, array{workspace: string, status: SubscriptionStatus, seat: ?string}> */
    private const SUBSCRIPTIONS = [
        'freelancer1' => ['workspace' => 'ws1', 'status' => SubscriptionStatus::Active, 'seat' => 'A1'],
        'freelancer2' => ['workspace' => 'ws1', 'status' => SubscriptionStatus::Active, 'seat' => 'A2'],
        'freelancer3' => ['workspace' => 'ws1', 'status' => SubscriptionStatus::Pending, 'seat' => null],
        'freelancer4' => ['workspace' => 'ws1', 'status' => SubscriptionStatus::Suspended, 'seat' => null],
        'freelancer5' => ['workspace' => 'ws1', 'status' => SubscriptionStatus::Expired, 'seat' => null],
        'freelancer6' => ['workspace' => 'ws1', 'status' => SubscriptionStatus::Cancelled, 'seat' => null],
        'freelancer7' => ['workspace' => 'ws2', 'status' => SubscriptionStatus::Active, 'seat' => 'B1'],
        'freelancer8' => ['workspace' => 'ws2', 'status' => SubscriptionStatus::Active, 'seat' => 'B2'],
    ];

    /** @var array<string, array{workspace: string, status: BookingStatus}> */
    private const BOOKINGS = [
        'freelancer1' => ['workspace' => 'ws1', 'status' => BookingStatus::Approved],
        'freelancer2' => ['workspace' => 'ws1', 'status' => BookingStatus::Approved],
        'freelancer3' => ['workspace' => 'ws1', 'status' => BookingStatus::Pending],
        'freelancer6' => ['workspace' => 'ws1', 'status' => BookingStatus::Pending],
        'freelancer7' => ['workspace' => 'ws2', 'status' => BookingStatus::Approved],
        'freelancer8' => ['workspace' => 'ws2', 'status' => BookingStatus::Approved],
        'freelancer9' => ['workspace' => 'ws2', 'status' => BookingStatus::Pending],
        'freelancer10' => ['workspace' => 'ws2', 'status' => BookingStatus::Rejected],
    ];

    public function seed(DemoContext $context): void
    {
        $seats = $this->seedSeats($context);
        $this->seedPackages($context);
        $this->seedSubscriptions($context, $seats);
        $this->seedBookings($context);
    }

    /** @return array<string, Seat> keyed by "<workspace>:<seat number>" */
    private function seedSeats(DemoContext $context): array
    {
        $seats = [];

        foreach (['ws1' => ['A1', 'A2', 'A3', 'A4'], 'ws2' => ['B1', 'B2', 'B3']] as $workspaceKey => $numbers) {
            foreach ($numbers as $index => $number) {
                $seats[$workspaceKey.':'.$number] = Seat::query()->create([
                    'workspace_id' => $context->workspace($workspaceKey)->id,
                    'seat_number' => $number,
                    'type' => $index % 2 === 0 ? SeatType::Fixed : SeatType::Flexible,
                    'status' => SeatStatus::Available,
                ]);
            }
        }

        return $seats;
    }

    private function seedPackages(DemoContext $context): void
    {
        $packages = [
            ['ws1', 'أساسي', 20, 0, false, 100], ['ws1', 'احترافي', 100, 10, true, null],
            ['ws2', 'أساسي', 50, 5, false, 200],
        ];

        foreach ($packages as [$workspaceKey, $name, $speed, $price, $unlimited, $limit]) {
            InternetPackage::query()->create([
                'workspace_id' => $context->workspace($workspaceKey)->id,
                'name' => $name,
                'speed_mbps' => $speed,
                'price' => $price,
                'data_limit_gb' => $limit,
                'is_unlimited' => $unlimited,
                'is_active' => true,
            ]);
        }
    }

    /** @param  array<string, Seat>  $seats */
    private function seedSubscriptions(DemoContext $context, array $seats): void
    {
        foreach (self::SUBSCRIPTIONS as $userKey => $definition) {
            $workspace = $context->workspace($definition['workspace']);
            $seat = $definition['seat'] === null ? null : $seats[$definition['workspace'].':'.$definition['seat']];
            $member = $context->user($userKey);

            Subscription::query()->create([
                'member_id' => $member->id,
                'workspace_id' => $workspace->id,
                'seat_id' => $seat?->id,
                'plan_type' => PlanType::Monthly,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => $this->endDateFor($definition['status']),
                'monthly_price' => $workspace->price_per_month,
                'status' => $definition['status'],
                'cancelled_at' => $definition['status'] === SubscriptionStatus::Cancelled ? now()->subWeek() : null,
            ]);

            $seat?->update(['status' => SeatStatus::Occupied, 'assigned_member_id' => $member->id]);
        }
    }

    private function endDateFor(SubscriptionStatus $status): ?string
    {
        return $status === SubscriptionStatus::Expired ? now()->subWeek()->toDateString() : null;
    }

    private function seedBookings(DemoContext $context): void
    {
        foreach (self::BOOKINGS as $userKey => $definition) {
            $workspace = $context->workspace($definition['workspace']);
            $decided = $definition['status'] !== BookingStatus::Pending;

            BookingRequest::query()->create([
                'member_id' => $context->user($userKey)->id,
                'workspace_id' => $workspace->id,
                'preferred_seat_type' => SeatType::Fixed->value,
                'plan_type' => PlanType::Monthly->value,
                'message' => 'أرغب بالانضمام إلى المساحة والحصول على مقعد ثابت.',
                'status' => $definition['status'],
                'reviewed_by' => $decided ? $workspace->owner_id : null,
                'rejection_reason' => $definition['status'] === BookingStatus::Rejected ? 'لا تتوفر مقاعد شاغرة حالياً.' : null,
                'reviewed_at' => $decided ? now()->subDays(2) : null,
            ]);
        }
    }
}
