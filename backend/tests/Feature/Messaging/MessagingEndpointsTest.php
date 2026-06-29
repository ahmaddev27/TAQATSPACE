<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Jobs\SendBroadcastMessage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MessagingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP coverage for the messaging surfaces: admin platform settings + broadcast,
 * and owner workspace broadcast. Asserts JSON envelope shape, masked secrets,
 * and that side effects are queued rather than sent inline.
 */
class MessagingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function messagingAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Permission::findOrCreate('manage_messaging', 'web');
        $admin->givePermissionTo('manage_messaging');

        return $admin;
    }

    private function configurePlatform(): void
    {
        app(MessagingSettingsService::class)->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'password' => 'pw'],
            'sms' => ['provider' => 'hotsms', 'username' => 'u', 'password' => 'p'],
        ]);
    }

    // ---- Admin platform settings --------------------------------------------

    public function test_admin_can_read_masked_platform_settings(): void
    {
        $this->configurePlatform();
        Sanctum::actingAs($this->messagingAdmin());

        $this->getJson('/api/admin/settings/messaging')
            ->assertOk()
            ->assertJsonPath('data.smtp.host', 'smtp.example.com')
            ->assertJsonPath('data.smtp.has_password', true)
            ->assertJsonMissingPath('data.smtp.password')
            ->assertJsonPath('data.sms.has_credentials', true)
            ->assertJsonMissingPath('data.sms.username');
    }

    public function test_admin_can_update_platform_settings_and_secret_is_not_returned(): void
    {
        Sanctum::actingAs($this->messagingAdmin());

        $this->putJson('/api/admin/settings/messaging', [
            'smtp' => [
                'host' => 'new.smtp',
                'port' => 587,
                'password' => 'topsecret',
                'encryption' => 'tls',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.smtp.host', 'new.smtp')
            ->assertJsonPath('data.smtp.has_password', true)
            ->assertJsonMissingPath('data.smtp.password');

        // Persisted decrypted value round-trips.
        $raw = app(MessagingSettingsService::class)->getPlatformRaw();
        $this->assertSame('topsecret', $raw['smtp']['password']);
    }

    public function test_update_rejects_invalid_sms_provider(): void
    {
        Sanctum::actingAs($this->messagingAdmin());

        $this->putJson('/api/admin/settings/messaging', [
            'sms' => ['provider' => 'unknown-provider'],
        ])->assertStatus(422)->assertJsonValidationErrors('sms.provider');
    }

    public function test_non_admin_cannot_read_platform_settings(): void
    {
        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->getJson('/api/admin/settings/messaging')->assertForbidden();
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/settings/messaging')->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/admin/settings/messaging')->assertUnauthorized();
    }

    // ---- Admin broadcast -----------------------------------------------------

    public function test_admin_broadcast_queues_jobs_and_returns_summary(): void
    {
        Bus::fake();
        $this->configurePlatform();
        User::factory()->count(2)->create();
        Sanctum::actingAs($this->messagingAdmin());

        $this->postJson('/api/admin/messaging/broadcast', [
            'channels' => ['email'],
            'audience' => 'all',
            'subject' => 'Hello',
            'body' => 'World',
        ])
            ->assertOk()
            ->assertJsonPath('data.queued.email', 3) // 2 + the admin itself
            ->assertJsonStructure(['data' => ['queued' => ['email', 'sms', 'total'], 'skipped'], 'message']);

        Bus::assertDispatched(SendBroadcastMessage::class, 3);
    }

    public function test_admin_broadcast_without_configured_channel_returns_422(): void
    {
        Bus::fake();
        User::factory()->create();
        Sanctum::actingAs($this->messagingAdmin());

        $this->postJson('/api/admin/messaging/broadcast', [
            'channels' => ['email'],
            'audience' => 'all',
            'subject' => 'Hello',
            'body' => 'World',
        ])->assertStatus(422);

        Bus::assertNothingDispatched();
    }

    public function test_admin_broadcast_validation_requires_subject_for_email(): void
    {
        $this->configurePlatform();
        Sanctum::actingAs($this->messagingAdmin());

        $this->postJson('/api/admin/messaging/broadcast', [
            'channels' => ['email'],
            'audience' => 'all',
            'body' => 'World',
        ])->assertStatus(422)->assertJsonValidationErrors('subject');
    }

    public function test_specific_audience_requires_user_ids(): void
    {
        $this->configurePlatform();
        Sanctum::actingAs($this->messagingAdmin());

        $this->postJson('/api/admin/messaging/broadcast', [
            'channels' => ['email'],
            'audience' => 'specific',
            'subject' => 'Hi',
            'body' => 'World',
            'user_ids' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('user_ids');
    }

    // ---- Owner workspace broadcast ------------------------------------------

    public function test_owner_can_broadcast_to_own_members(): void
    {
        Bus::fake();
        $this->configurePlatform();

        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Subscription::factory()->count(2)->create(['workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/messaging/broadcast', [
            'channels' => ['email'],
            'audience' => 'all',
            'subject' => 'Hello',
            'body' => 'Members',
        ])
            ->assertOk()
            ->assertJsonPath('data.queued.email', 2);

        Bus::assertDispatched(SendBroadcastMessage::class, 2);
    }

    public function test_owner_without_workspace_gets_404(): void
    {
        Bus::fake();
        $this->configurePlatform();
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/workspace/messaging/broadcast', [
            'channels' => ['email'],
            'audience' => 'all',
            'subject' => 'Hi',
            'body' => 'B',
        ])->assertStatus(404);
    }

    public function test_owner_can_update_workspace_messaging_with_masked_response(): void
    {
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->putJson('/api/workspace/messaging', [
            'use_platform' => false,
            'smtp' => ['host' => 'own.smtp', 'password' => 'osecret'],
        ])
            ->assertOk()
            ->assertJsonPath('data.use_platform', false)
            ->assertJsonPath('data.smtp.host', 'own.smtp')
            ->assertJsonPath('data.smtp.has_password', true)
            ->assertJsonMissingPath('data.smtp.password');
    }
}
