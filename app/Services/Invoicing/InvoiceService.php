<?php

namespace App\Services\Invoicing;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceContact;
use App\Models\User;
use App\Models\Course;

/**
 * Two-step invoicing: the accounting system needs a CONTACT before it will take
 * an invoice. So a student with no contact is skipped by invoice-only tooling,
 * which is why a repair has to create the contact first.
 */
class InvoiceService
{
    public function __construct(private InvoiceGateway $gateway) {}

    /** Step 1: make sure the student has a contact. Safe to call repeatedly. */
    public function ensureContact(User $user): InvoiceContact
    {
        return InvoiceContact::where('user_id', $user->id)->first()
            ?? InvoiceContact::create([
                'user_id' => $user->id,
                'external_id' => $this->gateway->createContact($user->name, $user->email),
            ]);
    }

    /**
     * Step 2: create the draft invoice. Returns null (with the reason in $skipped)
     * when the student has no contact yet: it never invents one silently.
     */
    public function createDraftInvoice(Enrollment $enrollment, array $customFields = [], ?string &$skipped = null): ?Invoice
    {
        if ($existing = Invoice::where('enrollment_id', $enrollment->id)->first()) {
            return $existing; // idempotent: one invoice per enrollment
        }

        $contact = InvoiceContact::where('user_id', $enrollment->user_id)->first();
        if (! $contact) {
            $skipped = 'Student has no contact yet.';

            return null;
        }

        $fields = $this->keepValidDropdownValues($customFields);

        return Invoice::create([
            'enrollment_id' => $enrollment->id,
            'external_id' => $this->gateway->createDraftInvoice(
                $contact->external_id,
                Course::findOrFail($enrollment->course_id)->price,
                $fields,
            ),
            'amount' => Course::findOrFail($enrollment->course_id)->price,
            'custom_fields' => $fields,
        ]);
    }

    /**
     * A dropdown value that is not a real option would make the vendor reject
     * the whole invoice. Dropping just that field keeps the invoice itself.
     *
     * @return array<string, mixed>
     */
    public function keepValidDropdownValues(array $fields): array
    {
        $options = $this->gateway->dropdownOptions();

        return array_filter(
            $fields,
            fn ($value, $field) => ! array_key_exists($field, $options) || in_array($value, $options[$field], true),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
