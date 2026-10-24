<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyRequest extends FormRequest
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
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'organization_id' => ['required', Rule::exists('platform__organizations', 'id')->where('client_id', $tenantId)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'alpha_dash:ascii', 'max:60',
                Rule::unique('platform__companies', 'slug')->where('client_id', $tenantId)->ignore($this->route('company')),
            ],
            'cr_number' => ['nullable', 'digits_between:4,20'],
            'vat_number' => ['nullable', 'digits:15'],
            'unified_number' => ['nullable', 'digits_between:4,20'],
            'gosi_number' => ['nullable', 'string', 'max:30'],
            'qiwa_id' => ['nullable', 'string', 'max:30'],
            'street' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'digits:4'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'digits:5'],
            'timezone' => ['required', 'timezone:all'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
