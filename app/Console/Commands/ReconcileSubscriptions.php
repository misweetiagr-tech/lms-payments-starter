<?php

namespace App\Console\Commands;

use App\Models\PaymentSubscription;
use App\Services\Payments\SubscriptionReconciler;
use Illuminate\Console\Command;

class ReconcileSubscriptions extends Command
{
    protected $signature = 'payments:reconcile {--dry-run : Report what would change without writing} {--reference= : Only this subscription}';

    protected $description = 'Record subscription cycles the provider charged but the webhook never delivered.';

    public function handle(SubscriptionReconciler $reconciler): int
    {
        $query = PaymentSubscription::where('status', '!=', 'completed');

        if ($reference = $this->option('reference')) {
            $query->where('reference', $reference);
        }

        $total = 0;

        foreach ($query->cursor() as $subscription) {
            if ($this->option('dry-run')) {
                $missing = $reconciler->missingCycles($subscription);
                $this->line("{$subscription->reference}: {$missing} cycle(s) missing");
                $total += $missing;

                continue;
            }

            $recorded = $reconciler->reconcile($subscription);
            $this->line("{$subscription->reference}: recorded {$recorded} cycle(s)");
            $total += $recorded;
        }

        $this->info(($this->option('dry-run') ? 'Would record ' : 'Recorded ')."{$total} cycle(s).");

        return self::SUCCESS;
    }
}
