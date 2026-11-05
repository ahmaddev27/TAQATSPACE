<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Support\Network\LocalDays;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** The range to recalculate: at most three months, never in the workspace's future. Ownership is enforced by the controller. */
class RecalculateStatsRequest extends FormRequest
{
    public const MAX_MONTHS = 3;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::parse((string) $this->input('from'));

            if ((string) $this->input('to') > $this->localToday()) {
                $validator->errors()->add('to', __('messages.stats_range_in_future'));
            } elseif (CarbonImmutable::parse((string) $this->input('to'))->greaterThan($from->addMonthsNoOverflow(self::MAX_MONTHS))) {
                $validator->errors()->add('to', __('messages.stats_range_too_long', ['months' => self::MAX_MONTHS]));
            }
        });
    }

    private function localToday(): string
    {
        $days = new LocalDays;

        return $days->dateOf(now()->getTimestamp(), $days->zone($this->user()?->workspace?->timezone));
    }
}
