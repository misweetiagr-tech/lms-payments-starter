<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * In-memory stand-in for a real provider so the whole flow runs with no account.
 * Use markCharged() in tests or a tinker session to simulate the provider
 * charging a cycle (whether or not the webhook is ever delivered).
 */
class FakeGateway implements PaymentGateway
{
    public function createSubscription(int $amountPerCycle, int $totalCycles): string
    {
        $id = 'sub_'.Str::lower(Str::random(14));
        Cache::forever($this->key($id), 0);

        return $id;
    }

    public function paidCount(string $gatewaySubscriptionId): int
    {
        return (int) Cache::get($this->key($gatewaySubscriptionId), 0);
    }

    public function markCharged(string $gatewaySubscriptionId, int $paidCount): void
    {
        Cache::forever($this->key($gatewaySubscriptionId), $paidCount);
    }

    private function key(string $id): string
    {
        return "fake_gateway.$id.paid_count";
    }
}
