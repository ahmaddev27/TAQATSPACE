<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendBroadcastMessage;
use App\Models\MessageUsage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Messaging\BroadcastConfigException;
use App\Services\Messaging\BroadcastRequestData;
use App\Services\Messaging\BroadcastService;
use App\Services\MessagingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Coverage for audience resolution, channel guards, queueing, and usage logging.
 */
class BroadcastServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BroadcastService
    {
        return app(BroadcastService::class);
    }

    private function configurePlatform(): void
    {
        app(MessagingSettingsService::class)->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'password' => 'pw'],
            'sms' => ['provider' => 'hotsms', 'username' => 'u', 'password' => 'p'],
        ]);
    }

    /**
     * @param  list<'email'|'sms'>  $channels
     * @param  array{role?: ?string, status?: ?string}  $segment
     * @param  list<string>  $userIds
     */
    private function data(
        array $channels = ['email'],
        string $audience = 'all',
        array $segment = [],
        array $userIds = [],
        ?string $subject = 'Subject',
        string $body = 'Body',
    ): BroadcastRequestData {
        return new BroadcastRequestData(
            channels: $channels,
            audience: $audience,
            segment: $segment + ['role' => null, 'status' => null],
            userIds: $userIds,
            subject: $subject,
            body: $body,
        );
    }

    // ---- Channel guards -----------------------------------------------------

    public function test_platform_email_without_smtp_aborts_before_queueing(): void
    {
        Bus::fake();

        $this->expectException(BroadcastConfigException::class);
        try {
            $this->service()->broadcastFromPlatform($this->data(['email']));
        } finally {
            Bus::assertNothingDispatched();
        }
    }

    public function test_platform_sms_without_credentials_aborts(): void
    {
        Bus::fake();
        // Configure only SMTP, request SMS.
        app(MessagingSettingsService::class)->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'password' => 'pw'],
        ]);

        $this->expectException(BroadcastConfigException::class);
        $this->service()->broadcastFromPlatform($this->data(['sms']));
    }

    public function test_config_exception_carries_scope_specific_key(): void
    {
        Bus::fake();
        try {
            $this->service()->broadcastFromPlatform($this->data(['email']));
            $this->fail('Expected BroadcastConfigException');
        } catch (BroadcastConfigException $e) {
            $this->assertSame('messages.broadcast_email_not_configured_platform', $e->translationKey);
        }
    }

    // ---- Platform audience resolution ---------------------------------------

    public function test_platform_all_audience_queues_one_email_job_per_user(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->count(3)->create();

        $result = $this->service()->broadcastFromPlatform($this->data(['email'], 'all'));

        $this->assertSame(3, $result->queuedEmail);
        Bus::assertDispatched(SendBroadcastMessage::class, 3);
    }

    public function test_platform_segment_by_role_filters_recipients(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->count(2)->create(['role' => UserRole::Freelancer]);
        User::factory()->owner()->count(3)->create();

        $result = $this->service()->broadcastFromPlatform(
            $this->data(['email'], 'segment', ['role' => UserRole::WorkspaceOwner->value]),
        );

        $this->assertSame(3, $result->queuedEmail);
    }

    public function test_platform_segment_by_status_filters_recipients(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->count(2)->create(['status' => UserStatus::Active]);
        User::factory()->suspended()->count(4)->create();

        $result = $this->service()->broadcastFromPlatform(
            $this->data(['email'], 'segment', ['status' => UserStatus::Suspended->value]),
        );

        $this->assertSame(4, $result->queuedEmail);
    }

    public function test_platform_specific_audience_targets_only_chosen_ids(): void
    {
        Bus::fake();
        $this->configurePlatform();
        $targets = User::factory()->count(2)->create();
        User::factory()->count(5)->create();

        $result = $this->service()->broadcastFromPlatform(
            $this->data(['email'], 'specific', [], $targets->pluck('id')->all()),
        );

        $this->assertSame(2, $result->queuedEmail);
    }

    public function test_recipients_without_phone_are_skipped_for_sms(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->count(2)->create();
        User::factory()->create(['phone' => null]);

        $result = $this->service()->broadcastFromPlatform($this->data(['sms'], 'all'));

        $this->assertSame(2, $result->queuedSms);
        $this->assertSame(1, $result->skippedSms);
    }

    public function test_both_channels_queue_separate_jobs(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->count(2)->create();

        $result = $this->service()->broadcastFromPlatform($this->data(['email', 'sms'], 'all'));

        $this->assertSame(2, $result->queuedEmail);
        $this->assertSame(2, $result->queuedSms);
        Bus::assertDispatched(SendBroadcastMessage::class, 4);
    }

    public function test_queued_job_carries_decrypted_channel_config(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->create(['email' => 'target@example.com']);

        $this->service()->broadcastFromPlatform($this->data(['email'], 'all'));

        Bus::assertDispatched(SendBroadcastMessage::class, function (SendBroadcastMessage $job): bool {
            $r = new \ReflectionClass($job);
            $get = fn (string $p) => tap($r->getProperty($p), fn ($x) => $x->setAccessible(true))->getValue($job);

            return $get('channel') === 'email'
                && $get('to') === 'target@example.com'
                && $get('config')['host'] === 'smtp.example.com'
                && $get('config')['password'] === 'pw';
        });
    }

    // ---- Workspace audience + scoping + usage -------------------------------

    private function workspaceWithMembers(int $count, SubscriptionStatus $status = SubscriptionStatus::Active): Workspace
    {
        $workspace = Workspace::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            Subscription::factory()->create([
                'workspace_id' => $workspace->id,
                'status' => $status,
            ]);
        }

        return $workspace;
    }

    public function test_workspace_broadcast_only_targets_its_own_members(): void
    {
        Bus::fake();
        $this->configurePlatform();

        $workspace = $this->workspaceWithMembers(2);
        $this->workspaceWithMembers(5); // another workspace's members

        $result = $this->service()->broadcastFromWorkspace($workspace, $this->data(['email'], 'all'));

        $this->assertSame(2, $result->queuedEmail);
    }

    public function test_workspace_specific_ids_cannot_reach_other_workspace_members(): void
    {
        Bus::fake();
        $this->configurePlatform();

        $workspace = Workspace::factory()->create();
        $mine = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        $otherWorkspace = Workspace::factory()->create();
        $theirs = Subscription::factory()->create(['workspace_id' => $otherWorkspace->id]);

        // Pass both member ids; only the in-workspace one may be queued.
        $result = $this->service()->broadcastFromWorkspace(
            $workspace,
            $this->data(['email'], 'specific', [], [$mine->member_id, $theirs->member_id]),
        );

        $this->assertSame(1, $result->queuedEmail);
    }

    public function test_workspace_segment_by_subscription_status(): void
    {
        Bus::fake();
        $this->configurePlatform();

        $workspace = Workspace::factory()->create();
        Subscription::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active,
        ]);
        Subscription::factory()->count(3)->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Expired,
        ]);

        $result = $this->service()->broadcastFromWorkspace(
            $workspace,
            $this->data(['email'], 'segment', ['status' => SubscriptionStatus::Expired->value]),
        );

        $this->assertSame(3, $result->queuedEmail);
    }

    public function test_workspace_broadcast_records_platform_usage(): void
    {
        Bus::fake();
        $this->configurePlatform();

        // use_platform defaults true -> source = platform.
        $workspace = $this->workspaceWithMembers(2);

        $this->service()->broadcastFromWorkspace($workspace, $this->data(['email'], 'all'));

        $usage = MessageUsage::query()->where('workspace_id', $workspace->id)->get();
        $this->assertCount(1, $usage);
        $this->assertSame('email', $usage->first()->channel);
        $this->assertSame('platform', $usage->first()->source);
        $this->assertSame(2, $usage->first()->count);
    }

    public function test_workspace_broadcast_records_own_source_when_self_configured(): void
    {
        Bus::fake();
        $this->configurePlatform();

        $workspace = $this->workspaceWithMembers(1);
        app(MessagingSettingsService::class)->updateWorkspace($workspace, [
            'use_platform' => false,
            'smtp' => ['host' => 'own.smtp', 'password' => 'op'],
        ]);

        $this->service()->broadcastFromWorkspace($workspace->refresh(), $this->data(['email'], 'all'));

        $usage = MessageUsage::query()->where('workspace_id', $workspace->id)->first();
        $this->assertSame('own', $usage->source);
    }

    public function test_no_usage_row_when_nothing_queued(): void
    {
        Bus::fake();
        $this->configurePlatform();

        // Workspace with no members -> zero queued -> no usage row.
        $workspace = Workspace::factory()->create();

        $result = $this->service()->broadcastFromWorkspace($workspace, $this->data(['email'], 'all'));

        $this->assertSame(0, $result->queuedEmail);
        $this->assertSame(0, MessageUsage::query()->where('workspace_id', $workspace->id)->count());
    }
}
