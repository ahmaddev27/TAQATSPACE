<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\MessageUsage;
use App\Models\Workspace;

/**
 * Aggregates broadcast messaging usage per workspace, split by source (Taqat's
 * platform quota vs the workspace's own accounts) and channel (email / sms), so
 * the admin can see who is consuming the shared platform quota and how much.
 */
class AdminMessagingUsageService
{
    /**
     * @return array<int, array{
     *     workspace_id: string, workspace_name: string,
     *     platform_email: int, platform_sms: int, own_email: int, own_sms: int,
     *     platform_total: int
     * }>
     */
    public function summary(): array
    {
        $rows = MessageUsage::query()
            ->selectRaw('workspace_id, source, channel, SUM(count) as total')
            ->groupBy('workspace_id', 'source', 'channel')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $workspaces = Workspace::query()
            ->with('owner:id,avatar')
            ->whereIn('id', $rows->pluck('workspace_id')->unique())
            ->get(['id', 'name', 'owner_id', 'logo_path'])
            ->keyBy('id');

        $byWorkspace = [];

        foreach ($rows as $row) {
            $id = (string) $row->workspace_id;
            $workspace = $workspaces->get($id);

            $byWorkspace[$id] ??= [
                'workspace_id' => $id,
                'workspace_name' => (string) ($workspace?->name ?? '—'),
                // Dedicated workspace logo, falling back to the owner avatar.
                'workspace_logo' => $workspace?->logoUrl(),
                'platform_email' => 0,
                'platform_sms' => 0,
                'own_email' => 0,
                'own_sms' => 0,
                'platform_total' => 0,
            ];

            $key = $row->source.'_'.$row->channel; // e.g. platform_email

            if (array_key_exists($key, $byWorkspace[$id])) {
                $byWorkspace[$id][$key] = (int) $row->total;
            }
        }

        $result = array_values($byWorkspace);

        foreach ($result as &$entry) {
            $entry['platform_total'] = $entry['platform_email'] + $entry['platform_sms'];
        }
        unset($entry);

        // Heaviest platform-quota consumers first.
        usort($result, static fn (array $a, array $b): int => $b['platform_total'] <=> $a['platform_total']);

        return $result;
    }
}
