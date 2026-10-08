<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentSubscription;
use App\Services\InstallmentPlanResolver;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SubscriptionReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Start an installment subscription. The amount comes from the course and
     * plan on the server, never from the request.
     * (A real app takes the user from auth; user_id is in the body to keep the demo small.)
     */
    public function store(Request $request, InstallmentPlanResolver $plans, PaymentGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $course = Course::findOrFail($data['course_id']);

        // Server-side guard: a hand-made request cannot split a full-payment course.
        if (! $plans->isEnabled($course->id)) {
            return response()->json(['error' => 'This course is full payment only.'], 422);
        }

        $schedule = $plans->schedule($course->id, $course->price);
        $count = count($schedule);
        $perCycle = $schedule[0]['amount'];

        $subscription = DB::transaction(function () use ($data, $gateway, $count, $perCycle) {
            Enrollment::firstOrCreate(
                ['user_id' => $data['user_id'], 'course_id' => $data['course_id']],
                ['status' => 'pending'],
            );

            return PaymentSubscription::create([
                'reference' => 'SUB-'.Str::upper(Str::random(10)),
                'user_id' => $data['user_id'],
                'course_id' => $data['course_id'],
                'gateway_subscription_id' => $gateway->createSubscription($perCycle, $count),
                'total_cycles' => $count,
                'amount_per_cycle' => $perCycle,
            ]);
        });

        return response()->json([
            'reference' => $subscription->reference,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'schedule' => $schedule,
        ], 201);
    }

    /**
     * Status check that heals itself: if our record is behind the provider
     * (a webhook was missed), catch up first, then answer.
     */
    public function status(string $reference, SubscriptionReconciler $reconciler): JsonResponse
    {
        $subscription = PaymentSubscription::where('reference', $reference)->firstOrFail();

        $healed = $subscription->isTerminal() ? 0 : $reconciler->reconcile($subscription);
        $subscription->refresh();

        return response()->json([
            'reference' => $subscription->reference,
            'status' => $subscription->status,
            'paid_count' => $subscription->paid_count,
            'total_cycles' => $subscription->total_cycles,
            'healed_cycles' => $healed,
        ]);
    }
}
