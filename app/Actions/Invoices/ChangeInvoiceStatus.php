<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

class ChangeInvoiceStatus
{
    /**
     * Which statuses each move starts from.
     *
     * @var array<string, list<InvoiceStatus>>
     */
    private const FROM = [
        'sent' => [InvoiceStatus::Draft],
        'paid' => [InvoiceStatus::Sent, InvoiceStatus::Overdue],
        'void' => [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Overdue],
    ];

    /**
     * Mark sent (saves the company details on the invoice), paid, or void.
     */
    public function handle(Invoice $invoice, InvoiceStatus $to): Invoice
    {
        if (! in_array($invoice->status, self::FROM[$to->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('validation.in', ['attribute' => __('admin.invoices.fields.status')])]);
        }

        $invoice->status = $to;

        match ($to) {
            InvoiceStatus::Sent => $invoice->forceFill(['sent_at' => now(), 'issuer' => Setting::issuer()]),
            InvoiceStatus::Paid => $invoice->forceFill(['paid_at' => now()]),
            default => null,
        };

        $invoice->save();

        return $invoice;
    }
}
