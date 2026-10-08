<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Console\Command;

class BackfillInvoices extends Command
{
    protected $signature = 'invoices:backfill {--commit : Actually write. Without it nothing changes} {--user= : Only this user id}';

    protected $description = 'Repair active enrollments that have no invoice: create the missing contact, then the draft invoice.';

    public function handle(InvoiceService $invoices): int
    {
        $query = Enrollment::where('status', 'active')
            ->whereNotIn('id', Invoice::select('enrollment_id'));

        if ($user = $this->option('user')) {
            $query->where('user_id', $user);
        }

        $commit = (bool) $this->option('commit');
        $count = 0;

        foreach ($query->cursor() as $enrollment) {
            $count++;
            $student = User::findOrFail($enrollment->user_id);
            $this->line("enrollment {$enrollment->id} ({$student->email})");

            if (! $commit) {
                continue;
            }

            // Two steps: a contact must exist before the invoice can be made.
            $invoices->ensureContact($student);
            $invoices->createDraftInvoice($enrollment);
        }

        $this->info($commit ? "Repaired {$count} enrollment(s)." : "Dry run: {$count} enrollment(s) need repair. Re-run with --commit.");

        return self::SUCCESS;
    }
}
