<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Throwable;

/**
 * Baydaq bills its clients through its own suite (I4): when an invoice is sent and a suite
 * company is chosen under Settings, the same lines are issued as a ZATCA e-invoice there.
 * A failure never stops the invoice going out; it stays without an e-invoice to retry.
 */
class IssueEinvoice
{
    public const SETTING = 'einvoicing_company_id';

    public function __construct(private InvoiceIssuer $issuer, private CurrentTenant $tenant) {}

    public function handle(Invoice $invoice): void
    {
        $companyId = (int) (Setting::valuesFor([self::SETTING])[self::SETTING] ?? 0);
        $clientId = $companyId > 0 ? Company::withoutGlobalScopes()->whereKey($companyId)->value('client_id') : null;

        if ($clientId === null || $invoice->einvoice_id !== null) {
            return;
        }

        $invoice->loadMissing('client', 'items');

        try {
            $issued = $this->tenant->runFor($clientId, fn () => $this->issuer->issueToBuyer(
                $companyId,
                ['name_ar' => $invoice->client->name_ar, 'name_en' => $invoice->client->name_en, 'email' => $invoice->client->contact_email, 'phone' => $invoice->client->contact_phone],
                $invoice->items->map(fn (InvoiceItem $item): array => ['description' => $item->description, 'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price, 'vat_rate' => (string) BigDecimal::of((string) $invoice->vat_rate)->multipliedBy(100)->toScale(2)])->all(),
                'baydaq.invoice',
                $invoice->number,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($issued !== null) {
            $invoice->forceFill(['einvoice_id' => $issued->id, 'einvoice_number' => $issued->number, 'einvoice_status' => $issued->status])->save();
        }
    }
}
