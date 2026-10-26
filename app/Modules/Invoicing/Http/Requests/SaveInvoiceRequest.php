<?php

namespace App\Modules\Invoicing\Http\Requests;

use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Enums\InvoiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => [Rule::requiredIf(! $this->route('invoice')), 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'kind' => ['required', Rule::enum(InvoiceKind::class)],
            'related_invoice_id' => ['nullable', 'integer'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'supply_date' => ['nullable', 'date'],
            'payment_means' => ['required', Rule::in(['10', '30', '42', '48'])],
            'note' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.vat_rate' => ['nullable', 'numeric', Rule::in(['0', '0.00', '5', '5.00', '15', '15.00'])],
        ];
    }
}
