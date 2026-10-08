<?php

namespace App\Services\Invoicing;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Behaves like a strict accounting API: a dropdown custom field with a value
 * that is not one of its options is rejected for the WHOLE invoice (like an
 * HTTP 400), which is exactly the failure InvoiceService guards against.
 */
class FakeInvoiceGateway implements InvoiceGateway
{
    /** @var list<array{contact: string, amount: int, fields: array}> */
    public array $invoices = [];

    public function createContact(string $name, string $email): string
    {
        return 'contact_'.Str::lower(Str::random(8));
    }

    public function createDraftInvoice(string $contactId, int $amount, array $customFields): string
    {
        foreach ($customFields as $field => $value) {
            $options = $this->dropdownOptions()[$field] ?? null;

            if ($options !== null && ! in_array($value, $options, true)) {
                throw new RuntimeException("Illegal value '{$value}' for dropdown field '{$field}'.");
            }
        }

        $this->invoices[] = ['contact' => $contactId, 'amount' => $amount, 'fields' => $customFields];

        return 'inv_'.Str::lower(Str::random(8));
    }

    public function dropdownOptions(): array
    {
        return [
            'lead_source' => ['Website', 'Referral', 'Walk-in'],
            'profession' => ['Student', 'Teacher', 'Other'],
        ];
    }
}
