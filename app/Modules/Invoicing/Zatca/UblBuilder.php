<?php

namespace App\Modules\Invoicing\Zatca;

use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use App\Modules\Platform\Data\CompanyRef;

/**
 * The UBL 2.1 XML of an invoice in ZATCA's profile. The signature block and the QR document
 * reference are inserted later by the Signer (before the supplier party), so this unsigned
 * document hashes to the same value as the signed one with those blocks removed.
 *
 * @see https://zatca.gov.sa/en/E-Invoicing/SystemsDevelopers/Documents/20230519_ZATCA_Electronic_Invoice_XML_Implementation_Standard_vF.pdf
 */
class UblBuilder
{
    /**
     * @param  array{name: string, vat_number: string, cr_number: ?string, street: ?string, building_number: ?string, district: ?string, city: ?string, postal_code: ?string}  $seller
     */
    public function build(Invoice $invoice, array $seller, int $counter, string $previousHash): string
    {
        $invoice->loadMissing(['lines', 'customer', 'related']);
        $currency = $invoice->currency;
        $money = fn ($value): string => number_format((float) $value, 2, '.', '');
        $esc = fn (?string $value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $isSimplified = $invoice->type === InvoiceType::Simplified;
        $profile = $isSimplified ? 'reporting:1.0' : 'reporting:1.0';
        $issueTime = $invoice->issue_time ?? '00:00:00';

        $billingReference = $invoice->kind !== InvoiceKind::Invoice && $invoice->related ? <<<XML
            <cac:BillingReference>
                <cac:InvoiceDocumentReference>
                    <cbc:ID>{$esc($invoice->related->number)}</cbc:ID>
                </cac:InvoiceDocumentReference>
            </cac:BillingReference>
        XML : '';

        $customer = $this->customerParty($invoice, $esc);
        $instruction = $invoice->kind !== InvoiceKind::Invoice ? "\n                <cbc:InstructionNote>{$esc($invoice->reason ?? '-')}</cbc:InstructionNote>" : '';
        $delivery = $invoice->supply_date ? "<cac:Delivery>\n                <cbc:ActualDeliveryDate>{$invoice->supply_date->toDateString()}</cbc:ActualDeliveryDate>\n            </cac:Delivery>" : '';

        $taxSubtotals = $invoice->lines->groupBy(fn (InvoiceLine $line) => $line->vat_category.'|'.$line->vat_rate)->map(function ($lines) use ($money, $currency) {
            $first = $lines->first();
            $taxable = $money($lines->sum(fn (InvoiceLine $line) => (float) $line->net));
            $tax = $money($lines->sum(fn (InvoiceLine $line) => (float) $line->vat));
            $percent = $money($first->vat_rate);
            $exemption = $first->vat_category === 'S' ? '' : "\n                        <cbc:TaxExemptionReasonCode>VATEX-SA-OOS</cbc:TaxExemptionReasonCode>";

            return <<<XML
                    <cac:TaxSubtotal>
                        <cbc:TaxableAmount currencyID="{$currency}">{$taxable}</cbc:TaxableAmount>
                        <cbc:TaxAmount currencyID="{$currency}">{$tax}</cbc:TaxAmount>
                        <cac:TaxCategory>
                            <cbc:ID>{$first->vat_category}</cbc:ID>
                            <cbc:Percent>{$percent}</cbc:Percent>{$exemption}
                            <cac:TaxScheme>
                                <cbc:ID>VAT</cbc:ID>
                            </cac:TaxScheme>
                        </cac:TaxCategory>
                    </cac:TaxSubtotal>
            XML;
        })->implode("\n");

        $lines = $invoice->lines->values()->map(function (InvoiceLine $line, int $index) use ($money, $currency, $esc): string {
            $id = $index + 1;
            $quantity = number_format((float) $line->quantity, 6, '.', '');
            $price = number_format((float) $line->unit_price, 4, '.', '');
            $roundingAmount = $money((float) $line->net + (float) $line->vat);
            $discount = (float) $line->discount > 0 ? <<<XML

                        <cac:AllowanceCharge>
                            <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                            <cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>
                            <cbc:Amount currencyID="{$currency}">{$money($line->discount)}</cbc:Amount>
                        </cac:AllowanceCharge>
            XML : '';

            return <<<XML
                <cac:InvoiceLine>
                    <cbc:ID>{$id}</cbc:ID>
                    <cbc:InvoicedQuantity unitCode="{$esc($line->unit_code)}">{$quantity}</cbc:InvoicedQuantity>
                    <cbc:LineExtensionAmount currencyID="{$currency}">{$money($line->net)}</cbc:LineExtensionAmount>
                    <cac:TaxTotal>
                        <cbc:TaxAmount currencyID="{$currency}">{$money($line->vat)}</cbc:TaxAmount>
                        <cbc:RoundingAmount currencyID="{$currency}">{$roundingAmount}</cbc:RoundingAmount>
                    </cac:TaxTotal>
                    <cac:Item>
                        <cbc:Name>{$esc($line->description)}</cbc:Name>
                        <cac:ClassifiedTaxCategory>
                            <cbc:ID>{$line->vat_category}</cbc:ID>
                            <cbc:Percent>{$money($line->vat_rate)}</cbc:Percent>
                            <cac:TaxScheme>
                                <cbc:ID>VAT</cbc:ID>
                            </cac:TaxScheme>
                        </cac:ClassifiedTaxCategory>
                    </cac:Item>
                    <cac:Price>
                        <cbc:PriceAmount currencyID="{$currency}">{$price}</cbc:PriceAmount>{$discount}
                    </cac:Price>
                </cac:InvoiceLine>
            XML;
        })->implode("\n");

        $taxExclusive = $money($invoice->subtotal - $invoice->discount);
        $rounding = (float) $invoice->rounding !== 0.0
            ? "\n                <cbc:PayableRoundingAmount currencyID=\"{$currency}\">{$money($invoice->rounding)}</cbc:PayableRoundingAmount>"
            : '';
        $note = $invoice->note ? "\n    <cbc:Note languageID=\"ar\">{$esc($invoice->note)}</cbc:Note>" : '';

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
            <cbc:ProfileID>{$profile}</cbc:ProfileID>
            <cbc:ID>{$esc($invoice->number)}</cbc:ID>
            <cbc:UUID>{$invoice->uuid}</cbc:UUID>
            <cbc:IssueDate>{$invoice->issue_date->toDateString()}</cbc:IssueDate>
            <cbc:IssueTime>{$issueTime}</cbc:IssueTime>
            <cbc:InvoiceTypeCode name="{$invoice->type->subtypeCode()}">{$invoice->kind->typeCode()}</cbc:InvoiceTypeCode>{$note}
            <cbc:DocumentCurrencyCode>{$currency}</cbc:DocumentCurrencyCode>
            <cbc:TaxCurrencyCode>SAR</cbc:TaxCurrencyCode>
            {$billingReference}
            <cac:AdditionalDocumentReference>
                <cbc:ID>ICV</cbc:ID>
                <cbc:UUID>{$counter}</cbc:UUID>
            </cac:AdditionalDocumentReference>
            <cac:AdditionalDocumentReference>
                <cbc:ID>PIH</cbc:ID>
                <cac:Attachment>
                    <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">{$previousHash}</cbc:EmbeddedDocumentBinaryObject>
                </cac:Attachment>
            </cac:AdditionalDocumentReference>
            <cac:AccountingSupplierParty>
                <cac:Party>
                    <cac:PartyIdentification>
                        <cbc:ID schemeID="CRN">{$esc($seller['cr_number'] ?? '')}</cbc:ID>
                    </cac:PartyIdentification>
                    <cac:PostalAddress>
                        <cbc:StreetName>{$esc($seller['street'] ?? '-')}</cbc:StreetName>
                        <cbc:BuildingNumber>{$esc($seller['building_number'] ?? '0000')}</cbc:BuildingNumber>
                        <cbc:CitySubdivisionName>{$esc($seller['district'] ?? '-')}</cbc:CitySubdivisionName>
                        <cbc:CityName>{$esc($seller['city'] ?? '-')}</cbc:CityName>
                        <cbc:PostalZone>{$esc($seller['postal_code'] ?? '00000')}</cbc:PostalZone>
                        <cac:Country>
                            <cbc:IdentificationCode>SA</cbc:IdentificationCode>
                        </cac:Country>
                    </cac:PostalAddress>
                    <cac:PartyTaxScheme>
                        <cbc:CompanyID>{$esc($seller['vat_number'])}</cbc:CompanyID>
                        <cac:TaxScheme>
                            <cbc:ID>VAT</cbc:ID>
                        </cac:TaxScheme>
                    </cac:PartyTaxScheme>
                    <cac:PartyLegalEntity>
                        <cbc:RegistrationName>{$esc($seller['name'])}</cbc:RegistrationName>
                    </cac:PartyLegalEntity>
                </cac:Party>
            </cac:AccountingSupplierParty>
            {$customer}
            {$delivery}
            <cac:PaymentMeans>
                <cbc:PaymentMeansCode>{$esc($invoice->payment_means)}</cbc:PaymentMeansCode>{$instruction}
            </cac:PaymentMeans>
            <cac:TaxTotal>
                <cbc:TaxAmount currencyID="{$currency}">{$money($invoice->vat)}</cbc:TaxAmount>
        {$taxSubtotals}
            </cac:TaxTotal>
            <cac:TaxTotal>
                <cbc:TaxAmount currencyID="{$currency}">{$money($invoice->vat)}</cbc:TaxAmount>
            </cac:TaxTotal>
            <cac:LegalMonetaryTotal>
                <cbc:LineExtensionAmount currencyID="{$currency}">{$money($invoice->subtotal)}</cbc:LineExtensionAmount>
                <cbc:TaxExclusiveAmount currencyID="{$currency}">{$taxExclusive}</cbc:TaxExclusiveAmount>
                <cbc:TaxInclusiveAmount currencyID="{$currency}">{$money($invoice->total)}</cbc:TaxInclusiveAmount>
                <cbc:AllowanceTotalAmount currencyID="{$currency}">{$money($invoice->discount)}</cbc:AllowanceTotalAmount>
                <cbc:PrepaidAmount currencyID="{$currency}">0.00</cbc:PrepaidAmount>{$rounding}
                <cbc:PayableAmount currencyID="{$currency}">{$money($invoice->payableAmount())}</cbc:PayableAmount>
            </cac:LegalMonetaryTotal>
        {$lines}
        </Invoice>
        XML;
    }

    private function customerParty(Invoice $invoice, callable $esc): string
    {
        $customer = $invoice->customer;

        if ($invoice->type === InvoiceType::Simplified && $customer === null) {
            return "<cac:AccountingCustomerParty>\n                <cac:Party/>\n            </cac:AccountingCustomerParty>";
        }

        $taxScheme = $customer?->isVatRegistered() ? <<<XML

                    <cac:PartyTaxScheme>
                        <cbc:CompanyID>{$esc($customer->vat_number)}</cbc:CompanyID>
                        <cac:TaxScheme>
                            <cbc:ID>VAT</cbc:ID>
                        </cac:TaxScheme>
                    </cac:PartyTaxScheme>
        XML : <<<'XML'

                    <cac:PartyTaxScheme>
                        <cac:TaxScheme>
                            <cbc:ID>VAT</cbc:ID>
                        </cac:TaxScheme>
                    </cac:PartyTaxScheme>
        XML;

        return <<<XML
        <cac:AccountingCustomerParty>
                <cac:Party>
                    <cac:PostalAddress>
                        <cbc:StreetName>{$esc($customer?->street ?? '-')}</cbc:StreetName>
                        <cbc:BuildingNumber>{$esc($customer?->building_number ?? '0000')}</cbc:BuildingNumber>
                        <cbc:CitySubdivisionName>{$esc($customer?->district ?? '-')}</cbc:CitySubdivisionName>
                        <cbc:CityName>{$esc($customer?->city ?? '-')}</cbc:CityName>
                        <cbc:PostalZone>{$esc($customer?->postal_code ?? '00000')}</cbc:PostalZone>
                        <cac:Country>
                            <cbc:IdentificationCode>{$esc($customer?->country ?? 'SA')}</cbc:IdentificationCode>
                        </cac:Country>
                    </cac:PostalAddress>{$taxScheme}
                    <cac:PartyLegalEntity>
                        <cbc:RegistrationName>{$esc($customer?->name_ar ?? '-')}</cbc:RegistrationName>
                    </cac:PartyLegalEntity>
                </cac:Party>
            </cac:AccountingCustomerParty>
        XML;
    }

    /**
     * The seller block from a company: legal name, VAT and CR numbers and the national address.
     *
     * @return array{name: string, vat_number: string, cr_number: ?string, street: ?string, building_number: ?string, district: ?string, city: ?string, postal_code: ?string}
     */
    public static function seller(CompanyRef $company): array
    {
        return [
            'name' => $company->nameAr,
            'vat_number' => (string) $company->vatNumber,
            'cr_number' => $company->crNumber,
            ...$company->address,
        ];
    }
}
