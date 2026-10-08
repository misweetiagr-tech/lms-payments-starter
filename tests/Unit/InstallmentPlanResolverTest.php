<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\InstallmentPlan;
use App\Services\InstallmentPlanResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentPlanResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_course_with_no_plan_is_full_payment_only(): void
    {
        $course = Course::create(['title' => 'No plan', 'price' => 1000]);

        $this->assertFalse(app(InstallmentPlanResolver::class)->isEnabled($course->id));
    }

    public function test_a_disabled_plan_is_full_payment_only(): void
    {
        $course = Course::create(['title' => 'Off', 'price' => 1000]);
        InstallmentPlan::create(['course_id' => $course->id, 'enabled' => false, 'count' => 3]);

        $this->assertFalse(app(InstallmentPlanResolver::class)->isEnabled($course->id));
    }

    public function test_last_part_absorbs_the_rounding_remainder(): void
    {
        $course = Course::create(['title' => 'Odd', 'price' => 10000]);
        InstallmentPlan::create(['course_id' => $course->id, 'count' => 3]);

        $schedule = app(InstallmentPlanResolver::class)->schedule($course->id, 10000);

        $this->assertSame([3333, 3333, 3334], array_column($schedule, 'amount'));
        $this->assertSame(10000, array_sum(array_column($schedule, 'amount')));
    }

    public function test_interval_in_days_and_months(): void
    {
        $start = CarbonImmutable::parse('2025-01-31');

        $months = Course::create(['title' => 'M', 'price' => 900]);
        InstallmentPlan::create(['course_id' => $months->id, 'count' => 3, 'interval_value' => 1, 'interval_unit' => 'months']);

        $days = Course::create(['title' => 'D', 'price' => 900]);
        InstallmentPlan::create(['course_id' => $days->id, 'count' => 3, 'interval_value' => 14, 'interval_unit' => 'days']);

        $resolver = app(InstallmentPlanResolver::class);

        // Jan 31 + 1 month must not overflow into March.
        $this->assertSame(['2025-01-31', '2025-02-28', '2025-03-31'], array_column($resolver->schedule($months->id, 900, $start), 'due_date'));
        $this->assertSame(['2025-01-31', '2025-02-14', '2025-02-28'], array_column($resolver->schedule($days->id, 900, $start), 'due_date'));
    }
}
