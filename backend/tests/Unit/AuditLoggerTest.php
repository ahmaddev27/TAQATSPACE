<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AuditActorType;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_actor_is_system_when_there_is_no_user(): void
    {
        $workspace = Workspace::factory()->create();

        $log = app(AuditLogger::class)->log('billing.cycle.ran', $workspace, changes: ['count' => 3]);

        $this->assertSame(AuditActorType::System, $log->actor_type);
        $this->assertNull($log->actor_id);
        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame(['count' => 3], $log->changes);
        $this->assertNotEmpty($log->request_id);
    }

    public function test_router_actor_type_is_kept_without_a_user(): void
    {
        $log = app(AuditLogger::class)->log('push.routed', actorType: AuditActorType::Router);

        $this->assertSame(AuditActorType::Router, $log->actor_type);
    }
}
