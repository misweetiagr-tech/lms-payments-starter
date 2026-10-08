<?php

namespace App\Services\Payments;

use App\Models\PaymentSubscription;

/**
 * Safety net for missed webhooks: ask the provider what it really charged and
 * record any cycles we have not seen. Safe to run any number of times.
 */
class SubscriptionReconciler
{
    public function __construct(
        private PaymentGateway $gateway,
        private SubscriptionFinalizer $finalizer,
    ) {}

    /** How many cycles are missing locally compared with the provider. */
    public function missingCycles(PaymentSubscription $subscription): int
    {
        $remote = min($this->gateway->paidCount($subscription->gateway_subscription_id), $subscription->total_cycles);

        return max(0, $remote - $subscription->payments()->count());
    }

    /** @return int number of cycles recorded */
    public function reconcile(PaymentSubscription $subscription): int
    {
        $remote = min($this->gateway->paidCount($subscription->gateway_subscription_id), $subscription->total_cycles);
        $recorded = 0;

        for ($cycle = 1; $cycle <= $remote; $cycle++) {
            $applied = $this->finalizer->applyCharge(
                $subscription,
                $cycle,
                "reconcile-{$subscription->reference}-{$cycle}",
                $subscription->amount_per_cycle,
                'reconcile',
            );

            $recorded += $applied ? 1 : 0;
        }

        return $recorded;
    }
}
