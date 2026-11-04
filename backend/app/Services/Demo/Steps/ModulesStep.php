<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceFeature;
use App\Models\Subscription;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;
use App\Services\FeatureFlagService;

/**
 * The module split the checks rely on: the first tenant and the two-factor tenant
 * have the network and finance_v2 flags on, the second tenant and the throwaway one
 * have them off. Goes through FeatureFlagService, so what a flag seeds on its own
 * (the expense categories of finance_v2) is seeded here too.
 */
class ModulesStep implements DemoSeedStep
{
    /** @var list<string> workspace keys that get both flags */
    private const FLAGGED = ['ws1', 'ws_stepup'];

    /** @var list<string> members of the two-factor tenant: invoices and receipts need customers */
    private const STEPUP_MEMBERS = ['freelancer9', 'freelancer10'];

    public function __construct(private readonly FeatureFlagService $features) {}

    public function seed(DemoContext $context): void
    {
        foreach (self::FLAGGED as $workspaceKey) {
            $this->features->update($context->workspace($workspaceKey), [
                WorkspaceFeature::Network->value => true,
                WorkspaceFeature::FinanceV2->value => true,
            ]);
        }

        $this->seedStepupMembers($context);
    }

    private function seedStepupMembers(DemoContext $context): void
    {
        $workspace = $context->workspace('ws_stepup');

        foreach (self::STEPUP_MEMBERS as $userKey) {
            Subscription::query()->create([
                'member_id' => $context->user($userKey)->id,
                'workspace_id' => $workspace->id,
                'plan_type' => PlanType::Monthly,
                'start_date' => now()->subMonth()->toDateString(),
                'monthly_price' => $workspace->price_per_month,
                'status' => SubscriptionStatus::Active,
            ]);
        }
    }
}
