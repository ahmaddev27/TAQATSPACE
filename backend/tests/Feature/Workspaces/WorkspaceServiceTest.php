<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceStatusChangedNotification;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit-style coverage of the WorkspaceService: create/update settings, status
 * transitions (with side effects), publish/unpublish, photos & logo storage,
 * and the read helpers. City scope basics live in CityFilterTest — not repeated.
 */
class WorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WorkspaceService
    {
        return app(WorkspaceService::class);
    }

    public function test_create_for_owner_starts_pending_and_denormalizes_city(): void
    {
        $owner = User::factory()->owner()->create();
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);

        $workspace = $this->service()->createForOwner((string) $owner->id, [
            'name' => 'Hub',
            'address' => 'Somewhere',
            'city_id' => $city->id,
            'total_seats' => 10,
            'price_per_month' => 30,
        ]);

        $this->assertSame(WorkspaceStatus::Pending, $workspace->status);
        $this->assertSame($owner->id, $workspace->owner_id);
        $this->assertSame('غزة', $workspace->city);
    }

    public function test_create_for_owner_rejects_a_second_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->expectException(RuntimeException::class);
        $this->service()->createForOwner((string) $owner->id, [
            'name' => 'Hub',
            'address' => 'Somewhere',
            'total_seats' => 10,
            'price_per_month' => 30,
        ]);
    }

    public function test_update_settings_ignores_protected_fields(): void
    {
        $workspace = Workspace::factory()->create([
            'status' => WorkspaceStatus::Active->value,
            'total_seats' => 12,
            'price_per_month' => 40,
        ]);
        $originalOwner = $workspace->owner_id;

        $updated = $this->service()->updateSettings($workspace, [
            'name' => 'New Name',
            'status' => WorkspaceStatus::Suspended->value,
            'owner_id' => 'someone-else',
            'total_seats' => 999,
            'price_per_month' => 1,
        ]);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame(WorkspaceStatus::Active, $updated->status);
        $this->assertSame($originalOwner, $updated->owner_id);
        $this->assertSame(12, $updated->total_seats);
    }

    public function test_change_status_to_suspended_pauses_active_subscriptions(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create(['status' => WorkspaceStatus::Active->value]);
        $member = User::factory()->freelancer()->create();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $this->service()->changeStatus($workspace, WorkspaceStatus::Suspended);

        $this->assertSame(SubscriptionStatus::Suspended->value, $subscription->refresh()->status->value);
    }

    public function test_change_status_to_active_promotes_a_pending_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['status' => UserStatus::PendingVerification->value]);
        $workspace = Workspace::factory()->pending()->create(['owner_id' => $owner->id]);

        $this->service()->changeStatus($workspace, WorkspaceStatus::Active);

        $this->assertSame(UserStatus::Active, $owner->refresh()->status);
    }

    public function test_change_status_notifies_the_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->pending()->create(['owner_id' => $owner->id]);

        $this->service()->changeStatus($workspace, WorkspaceStatus::Rejected);

        Notification::assertSentTo($owner, WorkspaceStatusChangedNotification::class);
    }

    public function test_change_status_back_to_pending_is_silent(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->service()->changeStatus($workspace, WorkspaceStatus::Pending);

        Notification::assertNothingSent();
    }

    public function test_publish_then_unpublish_toggles_published_at(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create(['published_at' => null]);

        $published = $this->service()->publish($workspace);
        $this->assertNotNull($published->published_at);
        $this->assertTrue($published->isPublished());

        $unpublished = $this->service()->unpublish($published);
        $this->assertNull($unpublished->published_at);
    }

    public function test_publish_is_idempotent(): void
    {
        $stamp = now()->subDay();
        $workspace = Workspace::factory()->create(['published_at' => $stamp]);

        $result = $this->service()->publish($workspace);

        $this->assertSame(
            $stamp->toDateTimeString(),
            $result->published_at->toDateTimeString(),
        );
    }

    public function test_add_photos_appends_and_enforces_the_cap(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create(['photos' => ['a.jpg']]);

        $updated = $this->service()->addPhotos($workspace, [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ]);

        $this->assertCount(3, $updated->photos);
        Storage::disk('public')->assertExists($updated->photos[1]);
    }

    public function test_add_photos_rejects_exceeding_the_cap(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create([
            'photos' => array_fill(0, 10, 'seed.jpg'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->addPhotos($workspace, [UploadedFile::fake()->image('x.jpg')]);
    }

    public function test_remove_photo_deletes_from_disk_and_json(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create(['photos' => []]);
        $workspace = $this->service()->addPhotos($workspace, [UploadedFile::fake()->image('p.jpg')]);
        $path = $workspace->photos[0];

        $updated = $this->service()->removePhoto($workspace, $path);

        $this->assertNotContains($path, $updated->photos);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_remove_photo_rejects_a_foreign_path(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create(['photos' => ['mine.jpg']]);

        $this->expectException(RuntimeException::class);
        $this->service()->removePhoto($workspace, 'not-mine.jpg');
    }

    public function test_set_logo_stores_and_replaces_the_previous_file(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create(['logo_path' => null]);

        $first = $this->service()->setLogo($workspace, UploadedFile::fake()->image('logo.png'));
        $firstPath = $first->logo_path;
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->service()->setLogo($first, UploadedFile::fake()->image('logo2.png'));

        $this->assertNotSame($firstPath, $second->logo_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($second->logo_path);
    }

    public function test_remove_logo_clears_the_reference_and_file(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create(['logo_path' => null]);
        $workspace = $this->service()->setLogo($workspace, UploadedFile::fake()->image('logo.png'));
        $path = $workspace->logo_path;

        $updated = $this->service()->removeLogo($workspace);

        $this->assertNull($updated->logo_path);
        Storage::disk('public')->assertMissing($path);
    }
}
