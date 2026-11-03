<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** SET-GEN: contact, bank/IBAN, time zone, currency and Wi-Fi on PUT /workspace/settings. */
class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'GB82 WEST 1234 5698 7654 32';

    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        return [$owner, $workspace];
    }

    public function test_owner_saves_general_settings_and_sees_them_back(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();

        $this->putJson('/api/workspace/settings', [
            'contact_email' => 'hello@space.test',
            'bank_name' => 'Bank of Palestine',
            'iban' => self::IBAN,
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'wifi_ssid' => 'Space-WiFi',
            'wifi_password' => 's3cret-pass',
        ])
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Riyadh')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.iban', 'GB82WEST12345698765432')
            ->assertJsonPath('data.wifi_password', 's3cret-pass')
            ->assertJsonPath('data.contact_email', 'hello@space.test');

        $this->assertSame('SAR', $workspace->fresh()->currency);
    }

    public function test_invalid_timezone_and_currency_are_rejected(): void
    {
        $this->ownerWithWorkspace();

        $this->putJson('/api/workspace/settings', ['timezone' => 'Mars/Base', 'currency' => 'XXX'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['timezone', 'currency']);
    }

    public function test_invalid_iban_and_email_are_rejected(): void
    {
        $this->ownerWithWorkspace();

        $this->putJson('/api/workspace/settings', ['iban' => 'GB82WEST12345698765433', 'contact_email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iban', 'contact_email']);
    }

    public function test_wifi_password_is_encrypted_at_rest(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();

        $this->putJson('/api/workspace/settings', ['wifi_password' => 'plain-text-pw'])->assertOk();

        $raw = DB::table('workspaces')->where('id', $workspace->id)->value('wifi_password');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('plain-text-pw', (string) $raw);
        $this->assertSame('plain-text-pw', $workspace->fresh()->wifi_password);
    }

    public function test_bank_change_is_audited_with_masked_iban(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();

        $this->putJson('/api/workspace/settings', ['bank_name' => 'Bank A', 'iban' => self::IBAN])->assertOk();

        $log = AuditLog::query()->where('action', 'workspace.bank_details.changed')->firstOrFail();
        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame('****5432', $log->changes['iban']['to']);
        $this->assertStringNotContainsString('WEST', json_encode($log->changes));
    }

    public function test_unchanged_bank_details_are_not_audited(): void
    {
        $this->ownerWithWorkspace();

        $this->putJson('/api/workspace/settings', ['iban' => self::IBAN])->assertOk();
        $this->putJson('/api/workspace/settings', ['iban' => self::IBAN, 'currency' => 'USD'])->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', 'workspace.bank_details.changed')->count());
    }

    public function test_non_owner_cannot_update_general_settings(): void
    {
        $this->ownerWithWorkspace();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/workspace/settings', ['currency' => 'USD'])->assertForbidden();
    }

    public function test_public_payload_hides_private_general_fields(): void
    {
        $workspace = Workspace::factory()->create([
            'published_at' => now(),
            'iban' => 'GB82WEST12345698765432',
            'wifi_password' => 'hidden',
        ]);

        $this->getJson("/api/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.iban')
            ->assertJsonMissingPath('data.wifi_password');
    }
}
