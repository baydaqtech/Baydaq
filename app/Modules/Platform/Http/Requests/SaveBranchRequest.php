<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
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
        $branch = $this->route('branch');
        $companyId = $branch instanceof Branch ? $branch->company_id : $this->route('company')?->id;

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('platform__branches', 'code')->where('company_id', $companyId)->ignore($branch),
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'city_ar' => ['nullable', 'string', 'max:255'],
            'city_en' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
