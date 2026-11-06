<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\SubscriberSegment;
use App\Models\Workspace;
use App\Services\Network\ScoringConfig;
use App\Services\Network\SegmentClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SegmentClassifierTest extends TestCase
{
    /** @return array<string, array{0: float, 1: int, 2: int, 3: SubscriberSegment}> loyalty, days since last activity, tenure days, expected */
    public static function edgeCases(): array
    {
        return [
            'regular attender with a high score' => [85.0, 1, 200, SubscriberSegment::Champion],
            'champion threshold is inclusive' => [80.0, 0, 200, SubscriberSegment::Champion],
            'just under champion' => [79.99, 0, 200, SubscriberSegment::Regular],
            'regular threshold is inclusive' => [50.0, 3, 200, SubscriberSegment::Regular],
            'active but drifting' => [49.99, 3, 200, SubscriberSegment::AtRisk],
            '13 days without activity is still fine' => [90.0, 13, 200, SubscriberSegment::Champion],
            '14 days without activity is at risk' => [90.0, 14, 200, SubscriberSegment::AtRisk],
            '29 days is at risk' => [90.0, 29, 200, SubscriberSegment::AtRisk],
            '30 days is about to churn' => [90.0, 30, 200, SubscriberSegment::AboutToChurn],
            '59 days is about to churn' => [10.0, 59, 200, SubscriberSegment::AboutToChurn],
            '60 days is dormant' => [90.0, 60, 200, SubscriberSegment::Dormant],
            'a new subscriber' => [20.0, 2, 29, SubscriberSegment::New],
            'new ends at 30 days of tenure' => [20.0, 2, 30, SubscriberSegment::AtRisk],
            'inactivity beats being new' => [20.0, 14, 20, SubscriberSegment::AtRisk],
        ];
    }

    #[DataProvider('edgeCases')]
    public function test_segments_follow_the_rules(float $loyalty, int $inactiveDays, int $tenureDays, SubscriberSegment $expected): void
    {
        $segment = (new SegmentClassifier)->classify($loyalty, $inactiveDays, $tenureDays, ScoringConfig::forWorkspace(new Workspace));

        $this->assertSame($expected, $segment);
    }

    public function test_a_workspace_can_move_the_thresholds(): void
    {
        $workspace = new Workspace(['scoring' => ['thresholds' => ['at_risk_days' => 7]]]);

        $segment = (new SegmentClassifier)->classify(90.0, 7, 200, ScoringConfig::forWorkspace($workspace));

        $this->assertSame(SubscriberSegment::AtRisk, $segment);
    }
}
