<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendBroadcastMessage;
use App\Models\MessageUsage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MessagingSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Composes a broadcast (email and/or SMS) and fans it out to a chosen audience.
 *
 * Two entry points mirror the two compose surfaces:
 *  - {@see broadcastFromPlatform()} — admin → every/segmented/specific platform
 *    user, sent through Taqat's platform accounts.
 *  - {@see broadcastFromWorkspace()} — owner → their OWN workspace members only,
 *    sent through the workspace's accounts (or the platform when `use_platform`).
 *
 * The audience is resolved to a deduplicated recipient list, the relevant sender
 * config is resolved + verified once, then one {@see SendBroadcastMessage} job is
 * queued per recipient/channel. Recipients missing the needed contact field are
 * skipped and counted, never queued.
 */
class BroadcastService
{
    /** Queue dispatch in chunks so a huge audience never holds the request thread. */
    private const CHUNK = 500;

    public function __construct(
        private readonly MessagingSettingsService $settings,
    ) {}

    /**
     * Admin broadcast across platform users, sent via the PLATFORM config.
     *
     * @throws BroadcastConfigException when a requested channel is not configured
     */
    public function broadcastFromPlatform(BroadcastRequestData $data): BroadcastResult
    {
        $config = $this->settings->getPlatformRaw();

        $this->guardChannels($data, $config, 'platform');

        return $this->dispatch($data, $config, $this->platformRecipients($data));
    }

    /**
     * Owner broadcast across THIS workspace's members only, sent via the
     * workspace's resolved config (own accounts or platform per `use_platform`).
     *
     * @throws BroadcastConfigException when a requested channel is not configured
     */
    public function broadcastFromWorkspace(Workspace $workspace, BroadcastRequestData $data): BroadcastResult
    {
        $config = $this->settings->forWorkspace($workspace);

        $this->guardChannels($data, $config, 'workspace');

        $result = $this->dispatch($data, $config, $this->workspaceRecipients($workspace, $data));

        $this->recordUsage($workspace, $result);

        return $result;
    }

    /**
     * Log this broadcast's per-channel message counts, tagged by whether each
     * channel was sent through the workspace's own account or Taqat's platform
     * quota — so the admin can track shared-quota usage per workspace.
     */
    private function recordUsage(Workspace $workspace, BroadcastResult $result): void
    {
        $channels = [
            ['channel' => 'email', 'config' => 'smtp', 'count' => $result->queuedEmail],
            ['channel' => 'sms', 'config' => 'sms', 'count' => $result->queuedSms],
        ];

        foreach ($channels as $entry) {
            if ($entry['count'] <= 0) {
                continue;
            }

            MessageUsage::query()->create([
                'workspace_id' => $workspace->id,
                'channel' => $entry['channel'],
                'source' => $this->settings->channelSource($workspace, $entry['config']),
                'count' => $entry['count'],
            ]);
        }
    }

    // ---- Recipient resolution ----------------------------------------------

    /**
     * @return Collection<int, Recipient>
     */
    private function platformRecipients(BroadcastRequestData $data): Collection
    {
        $query = User::query()->select(['id', 'email', 'phone']);

        if ($data->audience === 'specific') {
            $query->whereIn('id', $data->userIds);
        } elseif ($data->audience === 'segment') {
            $this->applyUserSegment($query, $data->segment);
        }

        return $query->get()->map($this->toRecipient(...));
    }

    /**
     * Members of THIS workspace, resolved through their subscriptions and
     * deduplicated by user. Hard-scoped to the workspace so a `specific` id list
     * can never reach another workspace's users.
     *
     * @return Collection<int, Recipient>
     */
    private function workspaceRecipients(Workspace $workspace, BroadcastRequestData $data): Collection
    {
        $query = Subscription::query()
            ->where('subscriptions.workspace_id', $workspace->id)
            ->join('users', 'users.id', '=', 'subscriptions.member_id');

        if ($data->audience === 'specific') {
            $query->whereIn('subscriptions.member_id', $data->userIds);
        } elseif ($data->audience === 'segment' && ! empty($data->segment['status'])) {
            $query->where('subscriptions.status', $data->segment['status']);
        }

        return $query
            ->distinct()
            ->get(['users.id', 'users.email', 'users.phone'])
            ->map($this->toRecipient(...));
    }

    /**
     * @param  Builder<User>  $query
     * @param  array{role?: ?string, status?: ?string}  $segment
     */
    private function applyUserSegment(Builder $query, array $segment): void
    {
        if (! empty($segment['role']) && UserRole::tryFrom((string) $segment['role']) !== null) {
            $query->where('role', $segment['role']);
        }

        if (! empty($segment['status']) && UserStatus::tryFrom((string) $segment['status']) !== null) {
            $query->where('status', $segment['status']);
        }
    }

    private function toRecipient(object $row): Recipient
    {
        return new Recipient(
            email: is_string($row->email ?? null) ? $row->email : null,
            phone: is_string($row->phone ?? null) ? $row->phone : null,
        );
    }

    // ---- Dispatch -----------------------------------------------------------

    /**
     * @param  array{smtp: array<string, mixed>, sms: array<string, mixed>}  $config
     * @param  Collection<int, Recipient>  $recipients
     */
    private function dispatch(BroadcastRequestData $data, array $config, Collection $recipients): BroadcastResult
    {
        $queuedEmail = 0;
        $queuedSms = 0;
        $skippedEmail = 0;
        $skippedSms = 0;

        foreach ($recipients->chunk(self::CHUNK) as $chunk) {
            foreach ($chunk as $recipient) {
                if ($data->wantsEmail()) {
                    if ($recipient->email !== null) {
                        SendBroadcastMessage::dispatch(
                            'email',
                            $recipient->email,
                            (string) $data->subject,
                            $data->body,
                            $config['smtp'],
                        );
                        $queuedEmail++;
                    } else {
                        $skippedEmail++;
                    }
                }

                if ($data->wantsSms()) {
                    if ($recipient->phone !== null && $recipient->phone !== '') {
                        SendBroadcastMessage::dispatch(
                            'sms',
                            $recipient->phone,
                            (string) $data->subject,
                            $data->body,
                            $config['sms'],
                        );
                        $queuedSms++;
                    } else {
                        $skippedSms++;
                    }
                }
            }
        }

        return new BroadcastResult($queuedEmail, $queuedSms, $skippedEmail, $skippedSms);
    }

    // ---- Config guards ------------------------------------------------------

    /**
     * Ensure every requested channel has a usable sender account; otherwise abort
     * before queueing anything with a clear, scope-specific message.
     *
     * @param  array{smtp: array<string, mixed>, sms: array<string, mixed>}  $config  decrypted
     */
    private function guardChannels(BroadcastRequestData $data, array $config, string $scope): void
    {
        if ($data->wantsEmail() && ! $this->settings->isSmtpConfigured($config['smtp'])) {
            throw new BroadcastConfigException("messages.broadcast_email_not_configured_{$scope}");
        }

        if ($data->wantsSms() && ! $this->settings->isSmsConfigured($config['sms'])) {
            throw new BroadcastConfigException("messages.broadcast_sms_not_configured_{$scope}");
        }
    }
}
