<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceContact;
use App\Models\User;
use App\Services\Invoicing\FakeInvoiceGateway;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvoiceBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollmentWithoutContact(): Enrollment
    {
        $student = User::factory()->create();
        $course = Course::create(['title' => 'Demo', 'price' => 12000]);

        return Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
    }

    public function test_invoice_only_step_skips_a_student_with_no_contact(): void
    {
        $enrollment = $this->activeEnrollmentWithoutContact();

        $invoice = app(InvoiceService::class)->createDraftInvoice($enrollment, [], $skipped);

        $this->assertNull($invoice);
        $this->assertSame('Student has no contact yet.', $skipped);
        $this->assertSame(0, Invoice::count());
    }

    public function test_repair_creates_the_contact_first_then_the_invoice(): void
    {
        $enrollment = $this->activeEnrollmentWithoutContact();

        $this->artisan('invoices:backfill --commit')->assertSuccessful();

        $this->assertSame(1, InvoiceContact::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(12000, Invoice::first()->amount);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->activeEnrollmentWithoutContact();

        $this->artisan('invoices:backfill')->assertSuccessful();

        $this->assertSame(0, InvoiceContact::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_running_the_repair_twice_does_not_duplicate(): void
    {
        $this->activeEnrollmentWithoutContact();

        $this->artisan('invoices:backfill --commit')->assertSuccessful();
        $this->artisan('invoices:backfill --commit')->assertSuccessful();

        $this->assertSame(1, InvoiceContact::count());
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_dropdown_value_that_is_not_an_option_is_dropped_not_fatal(): void
    {
        $enrollment = $this->activeEnrollmentWithoutContact();
        $service = app(InvoiceService::class);
        $service->ensureContact(User::find($enrollment->user_id));

        $invoice = $service->createDraftInvoice($enrollment, ['lead_source' => 'Website', 'profession' => 'Astronaut']);

        // The valid field survives, the invalid one is skipped, the invoice still exists.
        $this->assertSame(['lead_source' => 'Website'], $invoice->custom_fields);
    }

    public function test_the_strict_vendor_really_would_reject_the_bad_value(): void
    {
        // Proves the guard above is needed: sent raw, the whole invoice fails.
        $this->expectException(RuntimeException::class);

        app(FakeInvoiceGateway::class)->createDraftInvoice('contact_x', 100, ['profession' => 'Astronaut']);
    }
}
