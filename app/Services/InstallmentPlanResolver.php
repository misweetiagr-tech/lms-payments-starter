<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use Carbon\CarbonImmutable;

/**
 * Single source of truth for "can this course be paid in installments, and how".
 *
 * Default is deliberately safe: a course with no plan row (or a disabled one)
 * is full payment only.
 */
class InstallmentPlanResolver
{
    /** @return array{enabled: bool, count: int, interval_value: int, interval_unit: string} */
    public function resolve(int $courseId): array
    {
        $plan = InstallmentPlan::where('course_id', $courseId)->first();

        if (! $plan || ! $plan->enabled || $plan->count < 2) {
            return ['enabled' => false, 'count' => 1, 'interval_value' => 0, 'interval_unit' => 'months'];
        }

        return [
            'enabled' => true,
            'count' => (int) $plan->count,
            'interval_value' => (int) $plan->interval_value,
            'interval_unit' => $plan->interval_unit,
        ];
    }

    public function isEnabled(int $courseId): bool
    {
        return $this->resolve($courseId)['enabled'];
    }

    /**
     * Split $total into parts. The last part absorbs the rounding remainder so
     * the parts always add up to exactly $total.
     *
     * @return list<array{no: int, amount: int, due_date: string}>
     */
    public function schedule(int $courseId, int $total, ?CarbonImmutable $start = null): array
    {
        $plan = $this->resolve($courseId);
        $start ??= CarbonImmutable::now();
        $count = $plan['count'];
        $base = intdiv($total, $count);

        $parts = [];
        for ($i = 1; $i <= $count; $i++) {
            $amount = $i === $count ? $total - $base * ($count - 1) : $base;
            $offset = ($i - 1) * $plan['interval_value'];
            $due = $plan['interval_unit'] === 'days'
                ? $start->addDays($offset)
                : $start->addMonthsNoOverflow($offset);

            $parts[] = ['no' => $i, 'amount' => $amount, 'due_date' => $due->toDateString()];
        }

        return $parts;
    }
}
