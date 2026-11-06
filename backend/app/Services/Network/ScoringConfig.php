<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\Workspace;

/**
 * The weights, targets and thresholds one workspace is scored with: the
 * defaults of config/scoring.php with the workspace's own overrides on top.
 * Anything in an override that is not a number is ignored, so a bad value can
 * never break the nightly run.
 */
final class ScoringConfig
{
    /** @param array<string, mixed> $values */
    private function __construct(private readonly array $values) {}

    public static function forWorkspace(Workspace $workspace): self
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('scoring');
        $overrides = is_array($workspace->scoring) ? $workspace->scoring : [];

        return new self(array_replace_recursive($defaults, self::numbersOnly($overrides)));
    }

    public function windowMonths(): int
    {
        return max(1, (int) $this->values['window_months']);
    }

    /** Weights of one score ('loyalty' or 'strength') scaled to add up to 1; the defaults when the overrides leave none. */
    public function weights(string $score): array
    {
        $weights = array_map(fn (mixed $weight): float => max(0.0, (float) $weight), $this->values['weights'][$score]);
        $total = array_sum($weights);

        if ($total <= 0.0) {
            $weights = $this->defaultWeights($score);
            $total = array_sum($weights);
        }

        return array_map(fn (float $weight): float => $weight / $total, $weights);
    }

    public function target(string $key): float
    {
        return max(1.0, (float) $this->values['targets'][$key]);
    }

    public function threshold(string $key): float
    {
        return (float) $this->values['thresholds'][$key];
    }

    /** @return array<string, float> */
    private function defaultWeights(string $score): array
    {
        return array_map('floatval', config('scoring.weights')[$score]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function numbersOnly(array $values): array
    {
        $kept = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $kept[$key] = self::numbersOnly($value);
            } elseif (is_int($value) || is_float($value)) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }
}
