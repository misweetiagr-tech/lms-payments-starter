<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    private PaymentSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.webhook_secret' => self::SECRET]);

        $user = User::factory()->create();
        $course = Course::create(['title' => 'Demo', 'price' => 30000]);
        InstallmentPlan::create(['course_id' => $course->id, 'count' => 3]);
        Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'pending']);

        $this->subscription = PaymentSubscription::create([
            'reference' => 'SUB-TEST',
            'user_id' => $user->id,
            'course_id' => $course->id,
            'gateway_subscription_id' => 'sub_test',
            'total_cycles' => 3,
            'amount_per_cycle' => 10000,
        ]);
    }

    private function send(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $body, self::SECRET);

        return $this->call('POST', '/api/webhooks/payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    private function charged(int $cycle, string $paymentId): array
    {
        return [
            'event' => 'subscription.charged',
            'data' => ['subscription_id' => 'sub_test', 'cycle' => $cycle, 'payment_id' => $paymentId, 'amount' => 10000],
        ];
    }

    public function test_rejects_a_bad_signature(): void
    {
        $this->send($this->charged(1, 'pay_1'), 'not-the-right-signature')->assertStatus(401);

        $this->assertSame(0, Payment::count());
    }

    public function test_first_charge_records_payment_and_activates_enrollment(): void
    {
        $this->send($this->charged(1, 'pay_1'))->assertOk()->assertJson(['applied' => true]);

        $this->assertSame(1, Payment::count());
        $this->assertSame('active', $this->subscription->fresh()->status);
        $this->assertSame('active', Enrollment::first()->status);
    }

    public function test_redelivering_the_same_event_does_not_double_count(): void
    {
        $this->send($this->charged(1, 'pay_1'))->assertOk();
        $this->send($this->charged(1, 'pay_1'))->assertOk()->assertJson(['applied' => false]);

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, $this->subscription->fresh()->paid_count);
    }

    public function test_same_cycle_with_a_different_payment_id_is_still_ignored(): void
    {
        $this->send($this->charged(1, 'pay_1'))->assertOk();
        $this->send($this->charged(1, 'pay_other'))->assertOk()->assertJson(['applied' => false]);

        $this->assertSame(1, Payment::count());
    }

    public function test_subscription_completes_after_the_last_cycle(): void
    {
        foreach ([1, 2, 3] as $cycle) {
            $this->send($this->charged($cycle, "pay_$cycle"))->assertOk();
        }

        $this->assertSame('completed', $this->subscription->fresh()->status);
        $this->assertSame(3, $this->subscription->fresh()->paid_count);
    }

    public function test_unknown_subscription_returns_202_so_the_provider_stops_retrying(): void
    {
        $payload = $this->charged(1, 'pay_1');
        $payload['data']['subscription_id'] = 'sub_unknown';

        $this->send($payload)->assertStatus(202);
    }

    public function test_missing_secret_is_a_server_error_not_a_pass(): void
    {
        config(['payments.webhook_secret' => '']);

        $this->send($this->charged(1, 'pay_1'), 'anything')->assertStatus(500);
    }
}
