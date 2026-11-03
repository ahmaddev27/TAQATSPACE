<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\HourPurchaseStatus;
use App\Exceptions\HourPackageException;
use App\Models\AuditLog;
use App\Models\HourLedgerEntry;
use App\Models\HourPackage;
use App\Models\HourPackagePurchase;
use App\Models\User;
use App\Services\Hours\HourBalanceService;
use App\Services\Hours\HourPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HourPackageEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function billingAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Permission::findOrCreate('manage_billing', 'web');
        $admin->givePermissionTo('manage_billing');

        return $admin;
    }

    private function package(array $attrs = []): HourPackage
    {
        return HourPackage::query()->create(array_merge([
            'code' => 'H8', 'name_ar' => 'ثماني ساعات', 'name_en' => '8 hours',
            'hours' => 8, 'price' => '10.00', 'currency' => 'USD',
        ], $attrs));
    }

    private function activationPayload(): array
    {
        return [
            'payment_method' => 'cash',
            'paid_at' => '2026-11-01',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_admin_creates_updates_and_archives_packages(): void
    {
        Sanctum::actingAs($this->billingAdmin());

        $id = $this->postJson('/api/admin/hour-packages', [
            'code' => 'H100', 'name_ar' => 'مئة ساعة', 'name_en' => '100 hours',
            'hours' => 100, 'price' => 100, 'currency' => 'USD',
        ])->assertCreated()->assertJsonPath('data.hours', 100)->json('data.id');

        $this->putJson("/api/admin/hour-packages/{$id}", ['price' => 90])
            ->assertOk()->assertJsonPath('data.price', '90.00');

        $this->deleteJson("/api/admin/hour-packages/{$id}")
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/admin/hour-packages')->assertOk()->assertJsonCount(1, 'data');
        foreach (['hours.package.created', 'hours.package.updated', 'hours.package.archived'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_package_validation_rejects_bad_input(): void
    {
        Sanctum::actingAs($this->billingAdmin());
        $this->package();

        $this->postJson('/api/admin/hour-packages', ['code' => 'H8', 'hours' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'hours', 'name_en', 'price']);
    }

    public function test_archived_package_cannot_be_requested_but_existing_purchases_survive(): void
    {
        $user = User::factory()->create();
        $package = $this->package();
        $purchase = app(HourPackageService::class)->requestPurchase($user, $package);

        app(HourPackageService::class)->archive($package);

        $this->assertSame(HourPurchaseStatus::Requested, $purchase->refresh()->status);
        $this->expectException(HourPackageException::class);
        app(HourPackageService::class)->requestPurchase($user, $package->refresh());
    }

    public function test_package_edits_do_not_change_existing_purchases(): void
    {
        $package = $this->package();
        $purchase = app(HourPackageService::class)->requestPurchase(User::factory()->create(), $package);

        app(HourPackageService::class)->update($package, ['hours' => 20, 'price' => '99.00']);

        $purchase->refresh();
        $this->assertSame(8, $purchase->hours);
        $this->assertSame('10.00', $purchase->price);
    }

    public function test_activation_credits_frozen_hours_exactly_once(): void
    {
        Storage::fake('public');
        $admin = $this->billingAdmin();
        $user = User::factory()->create();
        $purchase = app(HourPackageService::class)->requestPurchase($user, $this->package());
        $package = $purchase->package;
        $package->update(['hours' => 50]);
        Sanctum::actingAs($admin);

        $this->post("/api/admin/hour-purchases/{$purchase->id}/activate", $this->activationPayload(), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->post("/api/admin/hour-purchases/{$purchase->id}/activate", $this->activationPayload(), ['Accept' => 'application/json'])
            ->assertStatus(409);

        $this->assertSame(480, app(HourBalanceService::class)->balance($user));
        $this->assertSame(1, HourLedgerEntry::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($purchase->refresh()->receipt_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hours.purchase.activated']);
    }

    public function test_activation_sets_expiry_only_when_package_has_validity(): void
    {
        Storage::fake('public');
        $admin = $this->billingAdmin();
        $purchase = app(HourPackageService::class)->requestPurchase(
            User::factory()->create(),
            $this->package(['validity_days' => 30]),
        );
        Sanctum::actingAs($admin);

        $this->post("/api/admin/hour-purchases/{$purchase->id}/activate", $this->activationPayload(), ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertNotNull($purchase->refresh()->expires_at);
    }

    public function test_activation_requires_receipt_and_payment_method(): void
    {
        Sanctum::actingAs($this->billingAdmin());
        $purchase = app(HourPackageService::class)->requestPurchase(User::factory()->create(), $this->package());

        $this->postJson("/api/admin/hour-purchases/{$purchase->id}/activate", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method', 'receipt']);
    }

    public function test_cancel_marks_requested_purchase_and_blocks_later_activation(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->billingAdmin());
        $purchase = app(HourPackageService::class)->requestPurchase(User::factory()->create(), $this->package());

        $this->postJson("/api/admin/hour-purchases/{$purchase->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->post("/api/admin/hour-purchases/{$purchase->id}/activate", $this->activationPayload(), ['Accept' => 'application/json'])
            ->assertStatus(409);

        $this->assertDatabaseHas('audit_logs', ['action' => 'hours.purchase.cancelled']);
        $this->assertSame(0, HourLedgerEntry::query()->count());
    }

    public function test_queue_lists_purchases_with_stats_and_status_filter(): void
    {
        Sanctum::actingAs($this->billingAdmin());
        $service = app(HourPackageService::class);
        $package = $this->package();
        $service->requestPurchase(User::factory()->create(), $package);
        $service->requestPurchase(User::factory()->create(), $package)->update(['status' => 'cancelled']);

        $this->getJson('/api/admin/hour-purchases?status=requested')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.stats.requested', 1)
            ->assertJsonPath('data.stats.cancelled', 1);
    }

    public function test_adjustment_requires_a_reason_and_is_audited(): void
    {
        Sanctum::actingAs($this->billingAdmin());
        $user = User::factory()->create();

        $this->postJson('/api/admin/hour-adjustments', ['user_id' => $user->id, 'minutes' => 60])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->postJson('/api/admin/hour-adjustments', ['user_id' => $user->id, 'minutes' => 90, 'reason' => 'Goodwill'])
            ->assertCreated()->assertJsonPath('data.balance_minutes', 90);

        $this->postJson('/api/admin/hour-adjustments', ['user_id' => $user->id, 'minutes' => -500, 'reason' => 'Too much'])
            ->assertStatus(422);

        $this->assertSame('Goodwill', AuditLog::query()->where('action', 'hours.adjusted')->value('reason'));
    }

    public function test_every_endpoint_is_forbidden_without_manage_billing(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $package = $this->package();
        $purchase = app(HourPackageService::class)->requestPurchase(User::factory()->create(), $package);

        $this->getJson('/api/admin/hour-packages')->assertForbidden();
        $this->postJson('/api/admin/hour-packages', [])->assertForbidden();
        $this->putJson("/api/admin/hour-packages/{$package->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/hour-packages/{$package->id}")->assertForbidden();
        $this->getJson('/api/admin/hour-purchases')->assertForbidden();
        $this->postJson("/api/admin/hour-purchases/{$purchase->id}/activate", [])->assertForbidden();
        $this->postJson("/api/admin/hour-purchases/{$purchase->id}/cancel")->assertForbidden();
        $this->postJson('/api/admin/hour-adjustments', [])->assertForbidden();
    }

    public function test_ledger_entries_are_append_only(): void
    {
        $user = User::factory()->create();
        $entry = HourLedgerEntry::query()->create(['user_id' => $user->id, 'minutes' => 60, 'reason' => 'credit']);

        $this->expectException(\LogicException::class);
        $entry->update(['minutes' => 1]);
    }

    public function test_purchase_model_relation_is_reachable(): void
    {
        $purchase = app(HourPackageService::class)->requestPurchase(User::factory()->create(), $this->package());

        $this->assertInstanceOf(HourPackagePurchase::class, $purchase);
        $this->assertSame('H8', $purchase->package->code);
    }
}
