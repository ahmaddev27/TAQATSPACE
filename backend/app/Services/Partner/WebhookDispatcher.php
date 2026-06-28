<?php

declare(strict_types=1);

namespace App\Services\Partner;

use App\Jobs\DeliverWebhook;
use App\Models\BookingRequest;
use App\Models\PartnerClient;
use App\Models\Subscription;
use App\Models\WebhookDelivery;

/**
 * Builds booking webhook payloads and queues one delivery per active partner
 * that has registered a webhook URL. Booking flow stays decoupled from HTTP:
 * it calls a single method here; actual delivery happens off the request thread
 * via {@see DeliverWebhook}.
 */
class WebhookDispatcher
{
    public const EVENT_APPROVED = 'booking.approved';

    public const EVENT_REJECTED = 'booking.rejected';

    public function bookingApproved(BookingRequest $booking, ?Subscription $subscription): void
    {
        $this->dispatch(self::EVENT_APPROVED, $this->bookingData($booking, $subscription));
    }

    public function bookingRejected(BookingRequest $booking): void
    {
        $this->dispatch(self::EVENT_REJECTED, $this->bookingData($booking, null));
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingData(BookingRequest $booking, ?Subscription $subscription): array
    {
        $member = $booking->member;

        return array_filter([
            'student_sub' => $member?->sso_sub,
            'student_email' => $member?->email,
            'workspace_id' => $booking->workspace_id,
            'workspace_name' => $booking->workspace?->name,
            'booking_request_id' => $booking->id,
            'status' => $booking->status->value,
            'subscription_id' => $subscription?->id,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(string $event, array $data): void
    {
        $partners = PartnerClient::query()
            ->active()
            ->whereNotNull('webhook_url')
            ->get();

        foreach ($partners as $partner) {
            $delivery = WebhookDelivery::query()->create([
                'partner_client_id' => $partner->id,
                'event' => $event,
                'payload' => [
                    'event' => $event,
                    'occurred_at' => now()->toIso8601String(),
                    'data' => $data,
                ],
                'status' => 'pending',
            ]);

            DeliverWebhook::dispatch($delivery->id);
        }
    }
}
