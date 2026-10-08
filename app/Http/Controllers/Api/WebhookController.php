<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSubscription;
use App\Services\Payments\SubscriptionFinalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebhookController extends Controller
{
    public function handle(Request $request, SubscriptionFinalizer $finalizer): JsonResponse
    {
        $secret = (string) config('payments.webhook_secret');

        if ($secret === '') {
            // A missing secret is our misconfiguration, not the sender's fault.
            return response()->json(['error' => 'Webhook secret not configured.'], 500);
        }

        // Sign the RAW body: re-encoding parsed JSON would change the bytes.
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('X-Signature', ''))) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        if ($request->json('event') !== 'subscription.charged') {
            return response()->json(['ignored' => true]);
        }

        $subscription = PaymentSubscription::where('gateway_subscription_id', $request->json('data.subscription_id'))->first();

        if (! $subscription) {
            // Not ours (or not created yet). 202 stops the provider retrying forever.
            return response()->json(['ignored' => 'unknown subscription'], 202);
        }

        try {
            $applied = $finalizer->applyCharge(
                $subscription,
                (int) $request->json('data.cycle'),
                (string) $request->json('data.payment_id'),
                (int) $request->json('data.amount'),
                'webhook',
            );
        } catch (Throwable $e) {
            report($e);

            // A real failure returns 500 so the provider retries the delivery.
            return response()->json(['error' => 'Could not process event.'], 500);
        }

        // A duplicate delivery is still a success: 200 tells the provider to stop retrying.
        return response()->json(['applied' => $applied]);
    }
}
