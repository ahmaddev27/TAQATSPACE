<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auth and JSON-shape coverage for GET /api/workspace/reports — the owner
 * reports endpoint guarded by the `role.owner` middleware.
 */
class OwnerReportsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // OwnerReportsService groups P&L months with MySQL's DATE_FORMAT; sqlite
        // lacks it, so register an equivalent UDF for the happy-path request.
        DB::connection()->getPdo()->sqliteCreateFunction(
            'DATE_FORMAT',
            static function (?string $date, string $format): ?string {
                if ($date === null) {
                    return null;
                }

                $php = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

                return date($php, (int) strtotime($date));
            },
            2,
        );
    }

    /** Create an owner that actually owns a workspace. */
    private function ownerWithWorkspace(): User
    {
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);

        return $owner;
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/workspace/reports')->assertUnauthorized();
    }

    public function test_non_owner_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->getJson('/api/workspace/reports')->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/workspace/reports')->assertForbidden();
    }

    public function test_owner_without_a_workspace_gets_404(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->getJson('/api/workspace/reports')->assertNotFound();
    }

    public function test_owner_with_workspace_receives_the_full_report_shape(): void
    {
        Sanctum::actingAs($this->ownerWithWorkspace());

        $this->getJson('/api/workspace/reports')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'aging' => [['key', 'count', 'amount']],
                    'aging_total',
                    'profit_loss' => [['month', 'revenue', 'expenses', 'net']],
                    'package_uptake',
                ],
            ])
            ->assertJsonCount(5, 'data.aging')
            ->assertJsonCount(6, 'data.profit_loss');
    }
}
