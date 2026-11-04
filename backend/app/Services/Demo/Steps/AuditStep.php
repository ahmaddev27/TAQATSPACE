<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Services\Audit\AuditLogger;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;

/** A few audit entries so the audit pages have something to show for each tenant. */
class AuditStep implements DemoSeedStep
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function seed(DemoContext $context): void
    {
        $entries = [
            ['ws1', 'owner1', 'workspace.settings.updated', ['city' => 'غزة']],
            ['ws1', 'owner1', 'staff.invited', ['staff_group' => 'cashier']],
            ['ws1', 'staff_finance', 'pos.order.refunded', ['order_number' => 'DEMO-0006']],
            ['ws2', 'owner2', 'workspace.settings.updated', ['city' => 'غزة']],
            ['ws2', 'owner2', 'booking.rejected', ['reason' => 'no seats']],
        ];

        foreach ($entries as [$workspaceKey, $actorKey, $action, $changes]) {
            $workspace = $context->workspace($workspaceKey);

            $this->audit->log(
                action: $action,
                subject: $workspace,
                changes: $changes,
                actor: $context->user($actorKey),
                workspaceId: $workspace->id,
            );
        }
    }
}
