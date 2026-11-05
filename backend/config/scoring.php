<?php

declare(strict_types=1);

/*
 * Defaults for the loyalty and strength scores (task 3.7). A workspace can
 * override any key through `workspaces.scoring`, using the same structure.
 * Weights need not add up to 1: they are normalised per score. A changed
 * value takes effect at the next computation, never retroactively.
 */
return [
    // Months of statistics a score looks at, ending with the current local month.
    'window_months' => 3,

    'weights' => [
        'loyalty' => ['regularity' => 0.35, 'recency' => 0.30, 'tenure' => 0.15, 'engagement' => 0.20],
        'strength' => ['hours' => 0.40, 'days' => 0.35, 'contract' => 0.25],
    ],

    // The figure that earns a component its full 100 points.
    'targets' => [
        // Days since the last activity at which the recency component reaches 0.
        'recency_zero_days' => 30,
        // Days since the first activity that earn a full tenure component.
        'tenure_full_days' => 180,
        // Average active minutes on an attended day.
        'engagement_minutes_per_day' => 240,
        // Billable hours per month.
        'hours_per_month' => 40,
        // Attended days per month.
        'days_per_month' => 12,
    ],

    'thresholds' => [
        // Days without activity: at risk, about to churn, dormant.
        'at_risk_days' => 14,
        'churn_days' => 30,
        'dormant_days' => 60,
        // Days since the first activity during which a subscriber is still new.
        'new_days' => 30,
        // Loyalty score from which an active subscriber is a champion / a regular.
        'champion_score' => 80,
        'regular_score' => 50,
    ],
];
