<?php

namespace App\Services\Payments;

/**
 * The only things the app needs from a payment provider.
 * Swap the implementation (Razorpay, Stripe...) without touching the flow.
 */
interface PaymentGateway
{
    /** Create a recurring subscription and return the provider's id for it. */
    public function createSubscription(int $amountPerCycle, int $totalCycles): string;

    /** Ask the provider how many cycles it has actually charged. */
    public function paidCount(string $gatewaySubscriptionId): int;
}
