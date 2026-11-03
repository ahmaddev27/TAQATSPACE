<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CurrentWorkspaceResolverTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(?User $user = null): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_owner_resolves_to_the_workspace_they_own(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $resolved = app(CurrentWorkspaceResolver::class)->resolve($this->requestFor($owner));

        $this->assertSame($workspace->id, $resolved?->id);
    }

    public function test_staff_resolves_through_users_workspace_id(): void
    {
        $workspace = Workspace::factory()->create();
        $cashier = User::factory()->create(['role' => UserRole::Cashier, 'workspace_id' => $workspace->id]);

        $resolved = app(CurrentWorkspaceResolver::class)->resolve($this->requestFor($cashier));

        $this->assertSame($workspace->id, $resolved?->id);
    }

    public function test_router_workspace_wins_over_the_user(): void
    {
        $routed = Workspace::factory()->create();
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);
        $request = $this->requestFor($owner);
        $request->attributes->set(CurrentWorkspaceResolver::ROUTED_ATTRIBUTE, $routed);

        $resolved = app(CurrentWorkspaceResolver::class)->resolve($request);

        $this->assertSame($routed->id, $resolved?->id);
    }

    public function test_unlinked_user_and_guest_resolve_to_null(): void
    {
        $resolver = app(CurrentWorkspaceResolver::class);
        $freelancer = User::factory()->create(['role' => UserRole::Freelancer]);

        $this->assertNull($resolver->resolve($this->requestFor($freelancer)));
        $this->assertNull($resolver->resolve($this->requestFor()));
    }
}
