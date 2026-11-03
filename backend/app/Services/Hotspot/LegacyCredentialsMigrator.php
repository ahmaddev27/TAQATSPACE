<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Enums\HotspotAccountStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies subscriptions.internet_username / internet_password_enc into
 * hotspot_accounts. The password column is copied as ciphertext (same key and
 * cast), so nothing is decrypted; subscriptions that already have an account
 * are skipped, which makes a rerun a no-op.
 */
class LegacyCredentialsMigrator
{
    private const CHUNK_SIZE = 500;

    /** @return int accounts created by this run */
    public function migrate(): int
    {
        $created = 0;

        DB::table('subscriptions')
            ->whereNotNull('internet_username')
            ->whereNotNull('internet_password_enc')
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($chunk) use (&$created): void {
                $taken = DB::table('hotspot_accounts')
                    ->whereIn('subscription_id', $chunk->pluck('id'))
                    ->pluck('subscription_id')
                    ->all();

                $rows = $chunk->reject(fn ($row): bool => in_array($row->id, $taken, true))
                    ->map(fn ($row): array => $this->row($row))
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('hotspot_accounts')->insert($rows);
                    $created += count($rows);
                }
            });

        return $created;
    }

    /** @return array<string, mixed> */
    private function row(object $subscription): array
    {
        $live = $subscription->status === SubscriptionStatus::Active->value;

        return [
            'id' => (string) Str::uuid(),
            'workspace_id' => $subscription->workspace_id,
            'subscription_id' => $subscription->id,
            'username' => $subscription->internet_username,
            'password_enc' => $subscription->internet_password_enc,
            'profile' => null,
            'status' => ($live ? HotspotAccountStatus::Active : HotspotAccountStatus::Suspended)->value,
            'provisioned_at' => $subscription->internet_provisioned_at,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
