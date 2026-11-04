<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\InvoiceLifecycleService;

/** Shared fixtures for the finance-v2 invoice tests. */
abstract class InvoiceV2TestCase extends CatalogTestCase
{
    protected function subscriber(Workspace $workspace): User
    {
        $member = User::factory()->freelancer()->create();
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active,
        ]);

        return $member;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     * @return array<string, mixed>
     */
    protected function draftPayload(User $customer, ?array $lines = null, array $override = []): array
    {
        return [...[
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(10)->toDateString(),
            'lines' => $lines ?? [
                ['description' => 'Desk', 'qty' => '2', 'unit_price' => '10.50'],
                ['description' => 'Coffee', 'qty' => '3', 'unit_price' => '4.00'],
            ],
        ], ...$override];
    }

    protected function draftFor(User $owner, Workspace $workspace, ?User $customer = null, array $override = []): Invoice
    {
        $customer ??= $this->subscriber($workspace);

        return app(InvoiceLifecycleService::class)->createDraft($workspace, $this->draftPayload($customer, null, $override), $owner);
    }

    protected function issuedFor(User $owner, Workspace $workspace, ?User $customer = null): Invoice
    {
        return app(InvoiceLifecycleService::class)->issue($this->draftFor($owner, $workspace, $customer), $owner);
    }

    protected function openCharge(Workspace $workspace, User $customer, string $amount = '25.00'): UnbilledCharge
    {
        return UnbilledCharge::factory()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'qty' => '1.00',
            'unit_price' => $amount,
            'amount' => $amount,
            'description' => 'Printing',
        ]);
    }
}
