<?php

namespace App\Http\Requests\Admin;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    /**
     * New invoices need invoices.manage; editing is limited to drafts by the policy.
     */
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice ? $this->user()->can('update', $invoice) : $this->user()->can('create', Invoice::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('client_id', $this->input('client_id'))],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
