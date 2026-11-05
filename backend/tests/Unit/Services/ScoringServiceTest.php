<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Workspace;
use App\Repositories\Eloquent\StatsRepository;
use App\Repositories\Eloquent\SubscriberScoreRepository;
use App\Services\Network\ScoringConfig;
use App\Services\Network\ScoringService;
use App\Services\Network\SegmentClassifier;
use App\Services\Network\SubscriberActivity;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    private function service(): ScoringService
    {
        return new ScoringService(new SubscriberScoreRepository, new StatsRepository, new SegmentClassifier);
    }

    private function activity(int $inactive = 1, float $rateSum = 270.0, float $contract = 1.0): SubscriberActivity
    {
        return new SubscriberActivity(
            daysSinceLastActivity: $inactive,
            tenureDays: 200,
            spanMonths: 3,
            attendanceRateSum: $rateSum,
            attendanceDays: 54,
            activeMinutes: 54 * 300,
            hourBucketHours: 180,
            contractCoverage: $contract,
        );
    }

    public function test_a_subscriber_who_attends_regularly_is_a_champion(): void
    {
        $score = $this->service()->score($this->activity(), ScoringConfig::forWorkspace(new Workspace));

        $this->assertSame('champion', $score['segment']);
        $this->assertGreaterThanOrEqual(80.0, $score['loyalty']);
        $this->assertSame(100.0, $score['strength']);
    }

    public function test_no_activity_for_14_days_is_at_risk(): void
    {
        $score = $this->service()->score($this->activity(inactive: 14), ScoringConfig::forWorkspace(new Workspace));

        $this->assertSame('at_risk', $score['segment']);
    }

    public function test_the_same_inputs_give_the_same_score(): void
    {
        $config = ScoringConfig::forWorkspace(new Workspace);

        $this->assertSame($this->service()->score($this->activity(), $config), $this->service()->score($this->activity(), $config));
    }

    public function test_components_are_capped_at_100_and_floored_at_0(): void
    {
        $score = $this->service()->score($this->activity(inactive: 90, rateSum: 900.0), ScoringConfig::forWorkspace(new Workspace));

        $this->assertSame(100.0, $score['components']['loyalty']['regularity']);
        $this->assertSame(0.0, $score['components']['loyalty']['recency']);
    }

    public function test_weights_are_normalised_and_a_workspace_override_changes_the_score(): void
    {
        $default = ScoringConfig::forWorkspace(new Workspace);
        $recencyOnly = ScoringConfig::forWorkspace(new Workspace(['scoring' => ['weights' => ['loyalty' => ['regularity' => 0, 'recency' => 5, 'tenure' => 0, 'engagement' => 0]]]]));
        $activity = $this->activity(inactive: 15, rateSum: 60.0);

        $this->assertEqualsWithDelta(1.0, array_sum($recencyOnly->weights('loyalty')), 0.0001);
        $this->assertSame(50.0, $this->service()->score($activity, $recencyOnly)['loyalty']);
        $this->assertNotSame(50.0, $this->service()->score($activity, $default)['loyalty']);
    }

    public function test_unusable_overrides_fall_back_to_the_defaults(): void
    {
        $zero = ScoringConfig::forWorkspace(new Workspace(['scoring' => ['weights' => ['strength' => ['hours' => 0, 'days' => 0, 'contract' => 0]], 'targets' => ['hours_per_month' => 'lots']]]));

        $this->assertEqualsWithDelta(0.40, $zero->weights('strength')['hours'], 0.0001);
        $this->assertSame(40.0, $zero->target('hours_per_month'));
    }
}
