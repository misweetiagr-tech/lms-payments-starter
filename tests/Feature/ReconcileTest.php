<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileTest extends TestCase
{
    use RefreshDatabase;

    private PaymentSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.webhook_secret' => 'test-secret']);

        $user = User::factory()->create();
        $course = Course::create(['title' => 'Demo', 'price' => 30000]);
        InstallmentPlan::create(['course_id' => $course->id, 'count' => 3]);
        Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'pending']);

        $gateway = app(FakeGateway::class);
        $gatewayId = $gateway->createSubscription(10000, 3);

        $this->subscription = PaymentSubscription::create([
            'reference' => 'SUB-TEST',
            'user_id' => $user->id,
            'course_id' => $course->id,
            'gateway_subscription_id' => $gatewayId,
            'total_cycles' => 3,
            'amount_per_cycle' => 10000,
        ]);
    }

    public function test_command_records_cycles_the_webhook_missed(): void
    {
        // The provider charged two cycles but no webhook ever reached us.
        app(FakeGateway::class)->markCharged($this->subscription->gateway_subscription_id, 2);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(2, Payment::count());
        $this->assertSame('active', Enrollment::first()->status);
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_dry_run_writes_nothing(): void
    {
        app(FakeGateway::class)->markCharged($this->subscription->gateway_subscription_id, 2);

        $this->artisan('payments:reconcile --dry-run')->assertSuccessful();

        $this->assertSame(0, Payment::count());
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        app(FakeGateway::class)->markCharged($this->subscription->gateway_subscription_id, 3);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(3, Payment::count());
    }

    public function test_a_late_real_webhook_after_reconcile_is_not_double_counted(): void
    {
        app(FakeGateway::class)->markCharged($this->subscription->gateway_subscription_id, 1);
        $this->artisan('payments:reconcile')->assertSuccessful();

        $body = json_encode([
            'event' => 'subscription.charged',
            'data' => ['subscription_id' => $this->subscription->gateway_subscription_id, 'cycle' => 1, 'payment_id' => 'pay_real_1', 'amount' => 10000],
        ]);

        $this->call('POST', '/api/webhooks/payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'test-secret'),
        ], $body)->assertOk()->assertJson(['applied' => false]);

        $this->assertSame(1, Payment::count());
    }

    public function test_status_endpoint_heals_a_pending_subscription(): void
    {
        app(FakeGateway::class)->markCharged($this->subscription->gateway_subscription_id, 1);

        $this->getJson('/api/subscriptions/SUB-TEST/status')
            ->assertOk()
            ->assertJson(['status' => 'active', 'paid_count' => 1, 'healed_cycles' => 1]);

        $this->assertSame('active', Enrollment::first()->status);
    }
}
