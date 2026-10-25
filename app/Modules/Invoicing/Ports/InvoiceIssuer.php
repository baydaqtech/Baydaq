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
     * Issue a simplified (B2C) invoice through the company's unit. Returns the invoice even when
     * ZATCA rejected it (status "rejected") so the caller can keep the sale and retry later.
     *
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price_incl_vat: numeric-string|float|int, vat_rate?: numeric-string|float|int, discount_incl_vat?: numeric-string|float|int}>  $lines
     * @param  '10'|'30'|'42'|'48'  $paymentMeans
     */
    public function issueSimplified(int $companyId, int $unitId, array $lines, string $paymentMeans, ?string $note = null): IssuedInvoice;

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
