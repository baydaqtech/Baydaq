<?php

namespace App\Modules\Invoicing\Ports;

use App\Modules\Invoicing\Data\IssuedInvoice;

/**
 * Other modules (POS, bookings, memberships) issue tax invoices here. Lines carry prices
 * including VAT as the customer saw them; the issuer splits VAT out, signs and submits to ZATCA.
 */
interface InvoiceIssuer
{
    /**
     * Issue a simplified (B2C) invoice through the company's unit. Inside a transaction the
     * invoice is drafted now and sent to ZATCA once the caller commits, so the returned status
     * is "draft"; outside one it is sent at once. A rejection never undoes the caller's sale:
     * the invoice is kept (status "rejected") to be fixed and issued again.
     *
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price_incl_vat: numeric-string|float|int, vat_rate?: numeric-string|float|int, discount_incl_vat?: numeric-string|float|int}>  $lines
     * @param  '1'|'10'|'30'|'42'|'48'  $paymentMeans
     * @param  ?string  $source  the module that raised it (e.g. pos.order), whose own books record the sale
     */
    public function issueSimplified(int $companyId, int $unitId, array $lines, string $paymentMeans, ?string $note = null, ?string $source = null): IssuedInvoice;

    /**
     * A credit note against an issued invoice (a return at the counter), for the lines given at
     * their VAT-inclusive prices. Sent to ZATCA once the caller commits, like issueSimplified.
     * Null when the original was never accepted by ZATCA, so nothing can be credited against it.
     *
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price_incl_vat: numeric-string|float|int, vat_rate?: numeric-string|float|int}>  $lines
     * @param  '1'|'10'|'30'|'42'|'48'  $paymentMeans
     */
    public function creditNote(int $invoiceId, array $lines, string $paymentMeans, string $reason, string $source): ?int;

    /**
     * Draft an invoice from one company of the owner to another (a recharge). It stays a draft
     * for finance to review and issue; the buyer company becomes a customer once. Returns the
     * draft's id.
     *
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price: numeric-string|float|int, vat_rate?: numeric-string|float|int}>  $lines  net prices
     */
    public function draftIntercompany(int $sellerCompanyId, int $buyerCompanyId, array $lines, string $source, ?string $note = null): int;

    /**
     * Issue an invoice to an outside buyer known only by name (no VAT number, so a simplified
     * invoice), through the company's first ready unit. Null when the company has no ready unit.
     *
     * @param  array{name_ar: string, name_en?: string|null, email?: string|null, phone?: string|null}  $buyer
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price: numeric-string|float|int, vat_rate?: numeric-string|float|int}>  $lines  net prices
     */
    public function issueToBuyer(int $companyId, array $buyer, array $lines, string $source, ?string $note = null): ?IssuedInvoice;

    /** Whether the company has a unit able to issue right now (id of the first ready unit, or null). */
    public function readyUnitFor(int $companyId): ?int;

    /** @return list<array{id: int, name: string}> units of a company that hold a certificate */
    public function unitsFor(int $companyId): array;
}
