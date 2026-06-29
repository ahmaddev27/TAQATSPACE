<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Gender;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    public function test_by_city_counts_workspaces_and_active_members_per_city(): void
    {
        $riyadh = Workspace::factory()->create(['city' => 'Riyadh']);
        Workspace::factory()->create(['city' => 'Riyadh']);
        $jeddah = Workspace::factory()->create(['city' => 'Jeddah']);

        // Two active members in Riyadh, one cancelled (excluded), one in Jeddah.
        Subscription::factory()->create([
            'workspace_id' => $riyadh->id,
            'status' => SubscriptionStatus::Active,
        ]);
        Subscription::factory()->create([
            'workspace_id' => $riyadh->id,
            'status' => SubscriptionStatus::Active,
        ]);
        Subscription::factory()->cancelled()->create(['workspace_id' => $riyadh->id]);
        Subscription::factory()->create([
            'workspace_id' => $jeddah->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $byCity = collect($this->service()->build()['by_city'])->keyBy('label');

        $this->assertSame(2, $byCity['Riyadh']['workspaces']);
        $this->assertSame(2, $byCity['Riyadh']['active_members']);
        $this->assertSame(1, $byCity['Jeddah']['workspaces']);
        $this->assertSame(1, $byCity['Jeddah']['active_members']);
    }

    public function test_by_city_is_ordered_by_workspace_count_desc(): void
    {
        Workspace::factory()->count(3)->create(['city' => 'Riyadh']);
        Workspace::factory()->count(1)->create(['city' => 'Abha']);

        $byCity = $this->service()->build()['by_city'];

        $this->assertSame('Riyadh', $byCity[0]['label']);
        $this->assertSame(3, $byCity[0]['workspaces']);
        $this->assertSame('Abha', $byCity[1]['label']);
    }

    public function test_by_gender_buckets_are_zero_filled_and_ordered(): void
    {
        User::factory()->count(2)->create(['gender' => Gender::Male]);
        User::factory()->create(['gender' => Gender::Female]);
        User::factory()->count(3)->create(['gender' => null]);

        $byGender = $this->service()->build()['by_gender'];

        $this->assertSame(
            ['male', 'female', 'unspecified'],
            array_column($byGender, 'label'),
        );
        $this->assertSame(2, $byGender[0]['value']);
        $this->assertSame(1, $byGender[1]['value']);
        $this->assertSame(3, $byGender[2]['value']);
    }

    public function test_gender_buckets_helper_zero_fills_empty_counts(): void
    {
        $buckets = $this->service()->genderBuckets(new Collection);

        $this->assertSame(
            [
                ['label' => 'male', 'value' => 0],
                ['label' => 'female', 'value' => 0],
                ['label' => 'unspecified', 'value' => 0],
            ],
            $buckets,
        );
    }

    public function test_build_returns_both_breakdowns(): void
    {
        $result = $this->service()->build();

        $this->assertArrayHasKey('by_city', $result);
        $this->assertArrayHasKey('by_gender', $result);
    }
}
