<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Enums\AllowanceCalculation;
use App\Modules\Hr\Enums\DocumentFieldType;
use App\Modules\Hr\Enums\LeaveAccrual;
use App\Modules\Hr\Enums\LeaveYearBasis;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One request for the four HR catalogs; the rules beyond the bilingual name depend on which
 * catalog the route names.
 */
class SaveCatalogItemRequest extends FormRequest
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
        $common = [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        return [...$common, ...match ($this->catalog()) {
            'sections' => [
                'blocked_company_ids' => ['nullable', 'array'], 'blocked_company_ids.*' => ['integer'],
            ],
            'jobs' => [
                'section_ids' => ['required', 'array', 'min:1'], 'section_ids.*' => ['integer'],
                'blocked_company_ids' => ['nullable', 'array'], 'blocked_company_ids.*' => ['integer'],
                'default_role_id' => ['nullable', 'integer'],
            ],
            'allowance-types' => [
                'calculation' => ['required', Rule::enum(AllowanceCalculation::class)],
                'default_value' => ['required', 'numeric', 'min:0'],
                'counts_for_gosi' => ['boolean'],
            ],
            'bonus-types', 'deduction-types' => [],
            'leave-types' => [
                'days_per_year' => ['required', 'numeric', 'min:0', 'max:365'],
                'senior_days_per_year' => ['nullable', 'numeric', 'min:0', 'max:365', 'required_with:senior_after_years'],
                'senior_after_years' => ['nullable', 'integer', 'min:1', 'max:50', 'required_with:senior_days_per_year'],
                'accrual' => ['required', Rule::enum(LeaveAccrual::class)],
                'year_basis' => ['required', Rule::enum(LeaveYearBasis::class)],
                'carry_over_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
                'allow_negative' => ['boolean'],
                'is_paid' => ['boolean'],
                'needs_balance' => ['boolean'],
            ],
            'document-types' => [
                'has_number' => ['boolean'],
                'has_expiry' => ['boolean'],
                'alert_days' => ['nullable', 'array', 'max:5'], 'alert_days.*' => ['integer', 'min:1', 'max:730'],
                'renewal_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'fields' => ['nullable', 'array', 'max:20'],
                'fields.*.key' => ['nullable', 'string', 'max:40', 'alpha_dash:ascii'],
                'fields.*.label_ar' => ['required', 'string', 'max:100'],
                'fields.*.label_en' => ['required', 'string', 'max:100'],
                'fields.*.type' => ['required', Rule::enum(DocumentFieldType::class)],
                'fields.*.required' => ['boolean'],
                'fields.*.options' => ['nullable', 'array'],
                'fields.*.options.*' => ['string', 'max:100'],
            ],
        }];
    }

    public function catalog(): string
    {
        return (string) $this->route('catalog');
    }
}
