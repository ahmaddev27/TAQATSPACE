<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HTTP coverage of PhotoController and LogoController — owner-scoped media
 * mutations on the authenticated owner's single workspace.
 */
class PhotoAndLogoEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(array $attributes = []): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(array_merge(['owner_id' => $owner->id], $attributes));

        return [$owner, $workspace];
    }

    public function test_owner_can_upload_photos(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['photos' => []]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/photos', [
            'photos' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.photos');
    }

    public function test_photo_upload_validates_file_type(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/photos', [
            'photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);
    }

    public function test_owner_can_remove_a_photo(): void
    {
        Storage::fake('public');
        [$owner, $workspace] = $this->ownerWithWorkspace(['photos' => []]);
        Sanctum::actingAs($owner);
        $workspace = app(WorkspaceService::class)
            ->addPhotos($workspace, [UploadedFile::fake()->image('p.jpg')]);
        $path = $workspace->photos[0];

        $this->deleteJson('/api/workspace/photos', ['path' => $path])
            ->assertOk()
            ->assertJsonCount(0, 'data.photos');
    }

    public function test_remove_unknown_photo_returns_404(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['photos' => ['mine.jpg']]);
        Sanctum::actingAs($owner);

        $this->deleteJson('/api/workspace/photos', ['path' => 'ghost.jpg'])->assertNotFound();
    }

    public function test_photo_upload_without_a_workspace_returns_404(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/workspace/photos', [
            'photos' => [UploadedFile::fake()->image('p.jpg')],
        ])->assertNotFound();
    }

    public function test_owner_can_set_and_remove_logo(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace(['logo_path' => null]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.has_logo', true);

        $this->deleteJson('/api/workspace/logo')
            ->assertOk()
            ->assertJsonPath('data.has_logo', false);
    }

    public function test_logo_upload_validates_the_file(): void
    {
        Storage::fake('public');
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/logo', [
            'logo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_endpoint_without_a_workspace_returns_404(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/workspace/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertNotFound();
    }
}
