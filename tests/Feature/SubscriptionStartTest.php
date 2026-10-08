<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\InstallmentPlan;
use App\Models\PaymentSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_installments_are_created_from_the_server_side_plan(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => 'Demo', 'price' => 30000]);
        InstallmentPlan::create(['course_id' => $course->id, 'count' => 3]);

        $this->postJson('/api/subscriptions', ['user_id' => $user->id, 'course_id' => $course->id])
            ->assertCreated()
            ->assertJsonCount(3, 'schedule');

        $subscription = PaymentSubscription::first();
        $this->assertSame(3, $subscription->total_cycles);
        $this->assertSame(10000, $subscription->amount_per_cycle);
    }

    public function test_a_full_payment_only_course_cannot_be_split_by_a_crafted_request(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => 'No plan', 'price' => 30000]);

        $this->postJson('/api/subscriptions', ['user_id' => $user->id, 'course_id' => $course->id])
            ->assertStatus(422);

        $this->assertSame(0, PaymentSubscription::count());
    }
}
