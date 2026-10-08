<?php

namespace App\Services\Invoicing;

/**
 * What the app needs from an accounting system (Zoho Books style).
 * The app never talks to a vendor SDK directly, so tests run with no account.
 */
interface InvoiceGateway
{
    /** Create a customer contact and return the vendor's id. */
    public function createContact(string $name, string $email): string;

    /** Create a DRAFT invoice for a contact and return the vendor's id. */
    public function createDraftInvoice(string $contactId, int $amount, array $customFields): string;

    /**
     * Allowed values for each dropdown custom field, e.g.
     * ['lead_source' => ['Website', 'Referral']].
     *
     * @return array<string, list<string>>
     */
    public function dropdownOptions(): array;
}
