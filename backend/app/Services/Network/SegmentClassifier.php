<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\SubscriberSegment;

/**
 * Deterministic segment rules, checked in this order; the first that matches wins:
 *   1. no activity for dormant_days or more        -> dormant
 *   2. no activity for churn_days or more          -> about to churn
 *   3. no activity for at_risk_days or more        -> at risk
 *   4. first activity less than new_days ago       -> new
 *   5. loyalty at or above champion_score          -> champion
 *   6. loyalty at or above regular_score           -> regular
 *   7. anyone else is active but drifting          -> at risk
 */
class SegmentClassifier
{
    public function classify(float $loyaltyScore, int $daysSinceLastActivity, int $tenureDays, ScoringConfig $config): SubscriberSegment
    {
        $inactivity = [
            'dormant_days' => SubscriberSegment::Dormant,
            'churn_days' => SubscriberSegment::AboutToChurn,
            'at_risk_days' => SubscriberSegment::AtRisk,
        ];

        foreach ($inactivity as $threshold => $segment) {
            if ($daysSinceLastActivity >= $config->threshold($threshold)) {
                return $segment;
            }
        }

        if ($tenureDays < $config->threshold('new_days')) {
            return SubscriberSegment::New;
        }

        return match (true) {
            $loyaltyScore >= $config->threshold('champion_score') => SubscriberSegment::Champion,
            $loyaltyScore >= $config->threshold('regular_score') => SubscriberSegment::Regular,
            default => SubscriberSegment::AtRisk,
        };
    }
}
