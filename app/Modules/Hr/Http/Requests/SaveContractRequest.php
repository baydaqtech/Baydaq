<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Enums\AllowanceCalculation;
use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Enums\ContractType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContractRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ContractType::class)],
            'status' => ['required', Rule::enum(ContractStatus::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on', Rule::requiredIf(fn () => $this->input('type') === ContractType::Fixed->value)],
            'probation_days' => ['required', 'integer', 'min:0', 'max:180'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'allowances' => ['present', 'array'],
            'allowances.*.allowance_type_id' => ['required', 'integer'],
            'allowances.*.calculation' => ['required', Rule::enum(AllowanceCalculation::class)],
            'allowances.*.value' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ];
    }
}
