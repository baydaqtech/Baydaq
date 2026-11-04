<?php

namespace App\Modules\Invoicing\Adapters;

use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Ports\CustomerData;

class BuyerCustomerData implements CustomerData
{
    public function export(int $customerId): array
    {
        $buyers = Customer::query()->where('platform_customer_id', $customerId)->get();

        return [
            'invoice_buyers' => $buyers->map(fn (Customer $buyer): array => [
                'name_ar' => $buyer->name_ar, 'name_en' => $buyer->name_en, 'phone' => $buyer->phone, 'email' => $buyer->email,
                'vat_number' => $buyer->vat_number, 'city' => $buyer->city, 'since' => $buyer->created_at->toDateString(),
            ])->all(),
            'invoices' => Invoice::query()->whereIn('customer_id', $buyers->pluck('id'))->whereNotNull('number')->orderBy('issue_date')->get()
                ->map(fn (Invoice $invoice): array => ['number' => $invoice->number, 'date' => $invoice->issue_date->toDateString(), 'total' => (string) $invoice->total, 'status' => $invoice->status->value])->all(),
        ];
    }

    /**
     * Issued tax invoices are kept for ZATCA as they were; the buyer record behind them loses the
     * person's contact details, and a buyer with no VAT number loses the name too.
     */
    public function erase(int $customerId): int
    {
        $buyers = Customer::query()->where('platform_customer_id', $customerId)->get();

        foreach ($buyers as $buyer) {
            $buyer->forceFill([
                'phone' => null, 'email' => null,
                ...($buyer->vat_number ? [] : ['name_ar' => '—', 'name_en' => '—', 'street' => null, 'building_number' => null, 'district' => null, 'postal_code' => null]),
                'is_active' => false,
            ])->save();
        }

        return $buyers->count();
    }
}
