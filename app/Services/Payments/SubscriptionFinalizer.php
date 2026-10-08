<?php

namespace App\Services\Payments;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use Illuminate\Support\Facades\DB;

/**
 * The one place a charged cycle is recorded.
 *
 * Both the webhook and the reconcile job call this, so the rules live once:
 *  - a cycle is recorded at most once (unique per subscription + cycle number),
 *  - a provider payment id is recorded at most once,
 *  - the first paid cycle activates the enrollment.
 *
 * Returns true if this call recorded something, false if it was already known.
 */
class SubscriptionFinalizer
{
    public function applyCharge(
        PaymentSubscription $subscription,
        int $cycleNo,
        string $providerPaymentId,
        int $amount,
        string $source,
    ): bool {
        return DB::transaction(function () use ($subscription, $cycleNo, $providerPaymentId, $amount, $source) {
            // Serialise concurrent deliveries for the same subscription.
            $locked = PaymentSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $alreadyKnown = Payment::where('payment_subscription_id', $locked->id)->where('cycle_no', $cycleNo)->exists()
                || Payment::where('provider_payment_id', $providerPaymentId)->exists();

            if ($alreadyKnown) {
                return false;
            }

            Payment::create([
                'payment_subscription_id' => $locked->id,
                'cycle_no' => $cycleNo,
                'provider_payment_id' => $providerPaymentId,
                'amount' => $amount,
                'source' => $source,
            ]);

            $locked->paid_count = $locked->payments()->count();
            $locked->status = $locked->paid_count >= $locked->total_cycles ? 'completed' : 'active';
            $locked->save();

            Enrollment::updateOrCreate(
                ['user_id' => $locked->user_id, 'course_id' => $locked->course_id],
                ['status' => 'active'],
            );

            return true;
        });
    }
}
