<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\SiteSetting;
use App\Models\Workspace;
use App\Services\MessagingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Unit-level coverage for the messaging configuration service: secret
 * encryption at rest, masked output, preserve-blank merges, and the
 * platform/workspace resolution used by the send paths.
 */
class MessagingSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MessagingSettingsService
    {
        return app(MessagingSettingsService::class);
    }

    private function platformSetting(): ?SiteSetting
    {
        return SiteSetting::query()->where('key', 'messaging')->first();
    }

    // ---- Platform: persistence + encryption ---------------------------------

    public function test_update_platform_encrypts_secrets_at_rest(): void
    {
        $this->service()->updatePlatform([
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'mailer',
                'password' => 'super-secret',
                'encryption' => 'tls',
                'from_address' => 'noreply@example.com',
                'from_name' => 'Taqat',
            ],
            'sms' => [
                'provider' => 'hotsms',
                'sender' => 'TAQAT',
                'username' => 'sms-user',
                'password' => 'sms-pass',
            ],
        ]);

        $stored = $this->platformSetting()->value;

        // Plaintext must never be stored.
        $this->assertNotSame('super-secret', $stored['smtp']['password']);
        $this->assertNotSame('sms-pass', $stored['sms']['password']);
        $this->assertNotSame('sms-user', $stored['sms']['username']);

        // But it must round-trip back to the plaintext via Crypt.
        $this->assertSame('super-secret', Crypt::decryptString($stored['smtp']['password']));
        $this->assertSame('sms-pass', Crypt::decryptString($stored['sms']['password']));

        // Non-secret SMTP username is stored as-is.
        $this->assertSame('mailer', $stored['smtp']['username']);
    }

    public function test_get_platform_masks_secrets_and_exposes_has_flags(): void
    {
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'password' => 'secret'],
            'sms' => ['provider' => 'mtcsms', 'api_key' => 'key-123'],
        ]);

        $masked = $this->service()->getPlatform();

        $this->assertSame('smtp.example.com', $masked['smtp']['host']);
        $this->assertTrue($masked['smtp']['has_password']);
        $this->assertArrayNotHasKey('password', $masked['smtp']);

        $this->assertSame('mtcsms', $masked['sms']['provider']);
        $this->assertTrue($masked['sms']['has_credentials']);
        $this->assertArrayNotHasKey('api_key', $masked['sms']);
    }

    public function test_get_platform_raw_returns_decrypted_secrets(): void
    {
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'password' => 'secret'],
            'sms' => ['provider' => 'hotsms', 'username' => 'u', 'password' => 'p'],
        ]);

        $raw = $this->service()->getPlatformRaw();

        $this->assertSame('secret', $raw['smtp']['password']);
        $this->assertSame('u', $raw['sms']['username']);
        $this->assertSame('p', $raw['sms']['password']);
    }

    public function test_blank_secret_preserves_the_stored_value(): void
    {
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'password' => 'first-secret'],
        ]);

        // A masked round-trip submits no/blank password — it must not wipe it.
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'smtp.example.com', 'from_name' => 'Updated', 'password' => ''],
        ]);

        $raw = $this->service()->getPlatformRaw();
        $this->assertSame('first-secret', $raw['smtp']['password']);
        $this->assertSame('Updated', $raw['smtp']['from_name']);
    }

    public function test_empty_platform_has_no_secrets(): void
    {
        $masked = $this->service()->getPlatform();

        $this->assertFalse($masked['smtp']['has_password']);
        $this->assertFalse($masked['sms']['has_credentials']);
        $this->assertNull($masked['smtp']['host']);
    }

    // ---- Workspace: persistence + masking -----------------------------------

    public function test_update_workspace_defaults_use_platform_true(): void
    {
        $workspace = Workspace::factory()->create();

        $masked = $this->service()->updateWorkspace($workspace, [
            'smtp' => ['host' => 'own.smtp'],
        ]);

        $this->assertTrue($masked['use_platform']);
        $this->assertSame('own.smtp', $masked['smtp']['host']);
    }

    public function test_update_workspace_encrypts_and_masks(): void
    {
        $workspace = Workspace::factory()->create();

        $masked = $this->service()->updateWorkspace($workspace, [
            'use_platform' => false,
            'smtp' => ['host' => 'own.smtp', 'password' => 'wsecret'],
            'sms' => ['provider' => 'hotsms', 'username' => 'wuser'],
        ]);

        $this->assertFalse($masked['use_platform']);
        $this->assertTrue($masked['smtp']['has_password']);
        $this->assertArrayNotHasKey('password', $masked['smtp']);
        $this->assertTrue($masked['sms']['has_credentials']);

        $stored = $workspace->refresh()->messaging;
        $this->assertSame('wsecret', Crypt::decryptString($stored['smtp']['password']));
    }

    // ---- Resolution: forWorkspace + channelSource ---------------------------

    public function test_for_workspace_returns_platform_config_when_use_platform(): void
    {
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'platform.smtp', 'password' => 'pp'],
            'sms' => ['provider' => 'hotsms', 'username' => 'pu', 'password' => 'pw'],
        ]);

        $workspace = Workspace::factory()->create();
        $this->service()->updateWorkspace($workspace, [
            'use_platform' => true,
            'smtp' => ['host' => 'own.smtp', 'password' => 'op'],
        ]);

        $resolved = $this->service()->forWorkspace($workspace->refresh());

        $this->assertSame('platform.smtp', $resolved['smtp']['host']);
        $this->assertSame('pp', $resolved['smtp']['password']);
        $this->assertSame('platform', $this->service()->channelSource($workspace, 'smtp'));
        $this->assertSame('platform', $this->service()->channelSource($workspace, 'sms'));
    }

    public function test_for_workspace_uses_own_config_when_configured(): void
    {
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'platform.smtp', 'password' => 'pp'],
            'sms' => ['provider' => 'hotsms', 'username' => 'pu'],
        ]);

        $workspace = Workspace::factory()->create();
        $this->service()->updateWorkspace($workspace, [
            'use_platform' => false,
            'smtp' => ['host' => 'own.smtp', 'password' => 'op'],
            'sms' => ['provider' => 'mtcsms', 'api_key' => 'own-key'],
        ]);

        $resolved = $this->service()->forWorkspace($workspace->refresh());

        $this->assertSame('own.smtp', $resolved['smtp']['host']);
        $this->assertSame('op', $resolved['smtp']['password']);
        $this->assertSame('mtcsms', $resolved['sms']['provider']);
        $this->assertSame('own', $this->service()->channelSource($workspace, 'smtp'));
        $this->assertSame('own', $this->service()->channelSource($workspace, 'sms'));
    }

    public function test_for_workspace_falls_back_to_platform_per_channel(): void
    {
        $this->service()->updatePlatform([
            'smtp' => ['host' => 'platform.smtp', 'password' => 'pp'],
            'sms' => ['provider' => 'hotsms', 'username' => 'pu'],
        ]);

        // Own SMTP configured, but SMS left empty -> SMS must fall back.
        $workspace = Workspace::factory()->create();
        $this->service()->updateWorkspace($workspace, [
            'use_platform' => false,
            'smtp' => ['host' => 'own.smtp', 'password' => 'op'],
        ]);

        $resolved = $this->service()->forWorkspace($workspace->refresh());

        $this->assertSame('own.smtp', $resolved['smtp']['host']);
        $this->assertSame('hotsms', $resolved['sms']['provider']); // platform
        $this->assertSame('own', $this->service()->channelSource($workspace, 'smtp'));
        $this->assertSame('platform', $this->service()->channelSource($workspace, 'sms'));
    }

    // ---- "Is configured" guards ---------------------------------------------

    public function test_is_smtp_configured_requires_host(): void
    {
        $this->assertTrue($this->service()->isSmtpConfigured(['host' => 'smtp.example.com']));
        $this->assertFalse($this->service()->isSmtpConfigured([]));
        $this->assertFalse($this->service()->isSmtpConfigured(['host' => '']));
    }

    public function test_is_sms_configured_requires_provider_and_a_credential(): void
    {
        $this->assertTrue($this->service()->isSmsConfigured(['provider' => 'hotsms', 'username' => 'u']));
        $this->assertTrue($this->service()->isSmsConfigured(['provider' => 'mtcsms', 'api_key' => 'k']));
        $this->assertFalse($this->service()->isSmsConfigured(['provider' => 'hotsms']));
        $this->assertFalse($this->service()->isSmsConfigured(['username' => 'u']));
    }
}
