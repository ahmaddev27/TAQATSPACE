<?php

declare(strict_types=1);

namespace Tests\Feature\ExpensesResources;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\Workspace;
use App\Services\ResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ResourceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ResourceService
    {
        return app(ResourceService::class);
    }

    public function test_create_attaches_resource_to_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $resource = $this->service()->create($workspace, [
            'name' => 'Main Meeting Room',
            'type' => ResourceType::MeetingRoom->value,
            'quantity' => 2,
            'status' => ResourceStatus::Available->value,
            'notes' => null,
        ]);

        $this->assertSame($workspace->id, $resource->workspace_id);
        $this->assertSame('Main Meeting Room', $resource->name);
        $this->assertSame(ResourceType::MeetingRoom, $resource->type);
        $this->assertSame(2, $resource->quantity);
        $this->assertDatabaseHas('workspace_resources', [
            'id' => $resource->id,
            'workspace_id' => $workspace->id,
            'name' => 'Main Meeting Room',
        ]);
    }

    public function test_update_persists_changes_for_owned_resource(): void
    {
        $workspace = Workspace::factory()->create();
        $resource = Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => ResourceStatus::Available->value,
            'quantity' => 1,
        ]);

        $updated = $this->service()->update($workspace, $resource, [
            'status' => ResourceStatus::Maintenance->value,
            'quantity' => 5,
        ]);

        $this->assertSame(ResourceStatus::Maintenance, $updated->status);
        $this->assertSame(5, $updated->quantity);
        $this->assertDatabaseHas('workspace_resources', [
            'id' => $resource->id,
            'status' => ResourceStatus::Maintenance->value,
            'quantity' => 5,
        ]);
    }

    public function test_update_rejects_resource_from_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $foreign = Resource::factory()->create(['workspace_id' => $other->id]);

        $this->expectException(RuntimeException::class);

        $this->service()->update($workspace, $foreign, ['name' => 'Hacked']);
    }

    public function test_delete_removes_owned_resource(): void
    {
        $workspace = Workspace::factory()->create();
        $resource = Resource::factory()->create(['workspace_id' => $workspace->id]);

        $this->service()->delete($workspace, $resource);

        $this->assertDatabaseMissing('workspace_resources', ['id' => $resource->id]);
    }

    public function test_delete_rejects_resource_from_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $foreign = Resource::factory()->create(['workspace_id' => $other->id]);

        $this->expectException(RuntimeException::class);

        $this->service()->delete($workspace, $foreign);

        $this->assertDatabaseHas('workspace_resources', ['id' => $foreign->id]);
    }

    public function test_paginate_is_scoped_to_the_workspace_and_orders_newest_first(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        Resource::factory()->create(['workspace_id' => $other->id]);

        $first = Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'created_at' => now()->subDay(),
        ]);
        $second = Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'created_at' => now(),
        ]);

        $page = $this->service()->paginateForWorkspace($workspace, []);

        $this->assertSame(2, $page->total());
        $this->assertSame($second->id, $page->items()[0]->id);
        $this->assertSame($first->id, $page->items()[1]->id);
    }

    public function test_paginate_clamps_per_page_to_safe_bounds(): void
    {
        $workspace = Workspace::factory()->create();
        Resource::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $this->assertSame(200, $this->service()->paginateForWorkspace($workspace, ['per_page' => 5000])->perPage());
        $this->assertSame(1, $this->service()->paginateForWorkspace($workspace, ['per_page' => -10])->perPage());
    }

    public function test_filtered_query_applies_type_and_status(): void
    {
        $workspace = Workspace::factory()->create();

        $target = Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => ResourceType::MeetingRoom->value,
            'status' => ResourceStatus::Available->value,
        ]);
        Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => ResourceType::Parking->value,
            'status' => ResourceStatus::Available->value,
        ]);
        Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => ResourceType::MeetingRoom->value,
            'status' => ResourceStatus::Maintenance->value,
        ]);

        $page = $this->service()->paginateForWorkspace($workspace, [
            'type' => ResourceType::MeetingRoom->value,
            'status' => ResourceStatus::Available->value,
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame($target->id, $page->items()[0]->id);
    }

    public function test_filter_all_values_are_ignored(): void
    {
        $workspace = Workspace::factory()->create();
        Resource::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $page = $this->service()->paginateForWorkspace($workspace, [
            'type' => 'all',
            'status' => 'all',
        ]);

        $this->assertSame(3, $page->total());
    }

    public function test_summary_counts_by_status_with_zero_fill_and_total(): void
    {
        $workspace = Workspace::factory()->create();

        Resource::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'status' => ResourceStatus::Available->value,
        ]);
        Resource::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => ResourceStatus::Maintenance->value,
        ]);

        $summary = $this->service()->summaryForWorkspace($workspace);

        $this->assertSame(3, $summary['total']);
        foreach (ResourceStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $summary['by_status']);
        }
        $this->assertSame(2, $summary['by_status'][ResourceStatus::Available->value]);
        $this->assertSame(1, $summary['by_status'][ResourceStatus::Maintenance->value]);
        $this->assertSame(0, $summary['by_status'][ResourceStatus::Unavailable->value]);
    }

    public function test_summary_is_isolated_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();

        Resource::factory()->create(['workspace_id' => $workspace->id]);
        Resource::factory()->count(5)->create(['workspace_id' => $other->id]);

        $summary = $this->service()->summaryForWorkspace($workspace);

        $this->assertSame(1, $summary['total']);
    }
}
