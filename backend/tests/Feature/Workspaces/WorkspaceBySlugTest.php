<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceBySlugTest extends TestCase
{
    use RefreshDatabase;

    private function publicWorkspace(): Workspace
    {
        return Workspace::factory()->create(['status' => 'active', 'published_at' => now()]);
    }

    public function test_resolves_a_public_workspace_by_slug_without_identity_fields(): void
    {
        $workspace = $this->publicWorkspace();

        $this->getJson('/api/workspaces/by-slug/'.$workspace->slug)
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonMissingPath('data.slug')
            ->assertJsonMissingPath('data.urls');
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/workspaces/by-slug/does-not-exist')->assertNotFound();
    }

    public function test_unpublished_workspace_is_not_resolvable(): void
    {
        $workspace = Workspace::factory()->pending()->create();

        $this->getJson('/api/workspaces/by-slug/'.$workspace->slug)->assertNotFound();
    }

    public function test_owner_receives_dashboard_and_public_urls(): void
    {
        config(['app.frontend_url' => 'https://app.example.com/']);
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->putJson('/api/workspace/settings', [])
            ->assertOk()
            ->assertJsonPath('data.urls.dashboard', 'https://app.example.com/owner')
            ->assertJsonPath('data.urls.public', 'https://app.example.com/w/'.$workspace->slug);
    }
}
