<?php

declare(strict_types=1);

namespace Tests\Feature\WorkspacesExtra;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Photo/logo endpoint edge cases not covered by
 * tests/Feature/Workspaces/PhotoAndLogoEndpointTest: request-level validation
 * (empty/oversized batches, missing delete path), the photo-cap 422, webp
 * acceptance, and the logo-replacement happy path through HTTP.
 */
class PhotoAndLogoExtraTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(array $attributes = []): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(array_merge(['owner_id' => $owner->id], $attributes));

        return [$owner, $workspace];
    }

    public function test_photo_upload_requires_at_least_one_file(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['photos' => []]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/photos', ['photos' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_photo_upload_rejects_oversized_image(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['photos' => []]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/photos', [
            // 6 MB exceeds the 5120 KB cap.
            'photos' => [UploadedFile::fake()->image('big.jpg')->size(6000)],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);
    }

    public function test_photo_upload_accepts_webp(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['photos' => []]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/photos', [
            'photos' => [UploadedFile::fake()->image('shot.webp')],
        ])->assertCreated()->assertJsonCount(1, 'data.photos');
    }

    public function test_photo_upload_over_the_cap_returns_422(): void
    {
        Storage::fake('public');
        // Nine real stored paths already; adding two more would exceed the cap of 10.
        [$owner] = $this->ownerWithWorkspace(['photos' => array_fill(0, 9, 'workspaces/seed.jpg')]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/photos', [
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ])->assertStatus(422);
    }

    public function test_photo_delete_requires_a_path(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['photos' => []]);
        Sanctum::actingAs($owner);

        $this->deleteJson('/api/workspace/photos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['path']);
    }

    public function test_photo_delete_without_a_workspace_returns_404(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->deleteJson('/api/workspace/photos', ['path' => 'anything.jpg'])
            ->assertNotFound();
    }

    public function test_logo_replacement_swaps_the_file_through_http(): void
    {
        Storage::fake('public');
        [$owner, $workspace] = $this->ownerWithWorkspace(['logo_path' => null]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/logo', [
            'logo' => UploadedFile::fake()->image('first.png'),
        ])->assertCreated()->assertJsonPath('data.has_logo', true);

        $firstPath = $workspace->refresh()->logo_path;

        $this->postJson('/api/workspace/logo', [
            'logo' => UploadedFile::fake()->image('second.png'),
        ])->assertCreated()->assertJsonPath('data.has_logo', true);

        $secondPath = $workspace->refresh()->logo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_logo_upload_requires_a_file(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/logo', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_delete_without_a_workspace_returns_404(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->deleteJson('/api/workspace/logo')->assertNotFound();
    }
}
